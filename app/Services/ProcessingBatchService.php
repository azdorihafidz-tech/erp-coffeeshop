<?php

namespace App\Services;

use App\Enums\ProcessingMethod;
use App\Models\Cabang;
use App\Models\Item;
use App\Models\ProcessingBatch;
use Illuminate\Support\Facades\DB;

/**
 * Roastery V2 Minggu 3-4 — processing buah cherry jadi green bean. Alur:
 * draft → mulai (potong stok cherry) → fermentasi (Wine/Honey saja) → drying
 * → hulling → sortir → selesai (stok green bean masuk). Semua mutasi stok
 * lewat StokService existing (FIFO + movements, CLAUDE.md 3.3).
 *
 * 1 batch = 1 sumber cherry (item + qty tunggal), TIDAK bisa mix beberapa
 * item cherry dalam 1 batch — konsisten dengan pola CherryPurchase &
 * RoastingBatch (V1) yang juga 1 baris = 1 input. Beberapa batch BISA
 * berjalan paralel (tidak ada lock antar batch berbeda).
 */
class ProcessingBatchService
{
    public function __construct(private StokService $stokService) {}

    public static function roasteryCabangId(): int
    {
        return (int) Cabang::where('kode_cabang', 'RST001')->value('id');
    }

    public function startBatch(array $data, int $userId): ProcessingBatch
    {
        $item = Item::findOrFail($data['cherry_item_id']);
        $qty = (float) $data['cherry_qty_kg'];

        if ($qty <= 0) {
            throw new \Exception('Qty cherry harus lebih dari 0.');
        }

        $stokTersedia = $item->stokDiLokasi(self::roasteryCabangId());
        if ($stokTersedia < $qty) {
            throw new \Exception("Stok cherry '{$item->nama_item}' tidak mencukupi. Tersedia: {$stokTersedia} kg, dibutuhkan: {$qty} kg.");
        }

        return ProcessingBatch::create([
            'kode_batch'         => $this->nomorBerikutnya(),
            'tanggal_mulai'      => $data['tanggal_mulai'],
            'cabang_id'          => self::roasteryCabangId(),
            'user_id'            => $userId,
            'cherry_item_id'     => $item->id,
            'cherry_qty_kg'      => $qty,
            'processing_method'  => $data['processing_method'],
            'catatan_umum'       => $data['catatan_umum'] ?? null,
            'status'             => 'draft',
        ]);
    }

    /** Potong stok cherry, mulai tahap fermentasi (Wine/Honey) atau drying (Washed/Natural) langsung. */
    public function mulaiProcessing(int $batchId): ProcessingBatch
    {
        return DB::transaction(function () use ($batchId) {
            $batch = ProcessingBatch::lockForUpdate()->findOrFail($batchId);

            if ($batch->status !== 'draft') {
                throw new \Exception('Batch ini sudah dimulai (bukan draft lagi).');
            }

            $cost = $this->stokService->keluar(
                $batch->cherry_item_id,
                $batch->cabang_id,
                (float) $batch->cherry_qty_kg,
                "Processing {$batch->kode_batch} — cherry masuk pengolahan ({$batch->processing_method->label()})",
                'processing_batch',
                $batch->id
            );

            $method = $batch->processing_method;
            $nextStatus = $method->butuhFermentasi() ? 'fermentasi' : 'drying';
            $extra = $nextStatus === 'fermentasi'
                ? ['fermentasi_start' => now()]
                : ['drying_start' => now()];

            $batch->update(array_merge(['cherry_cost_awal' => round($cost, 2), 'status' => $nextStatus], $extra));

            return $batch->fresh();
        });
    }

    public function selesaiFermentasi(int $batchId, array $data): ProcessingBatch
    {
        $batch = ProcessingBatch::findOrFail($batchId);

        if ($batch->status !== 'fermentasi') {
            throw new \Exception('Batch ini bukan sedang berstatus fermentasi.');
        }

        $batch->update([
            'fermentasi_end'          => now(),
            'fermentasi_suhu_celsius' => $data['fermentasi_suhu_celsius'] ?? null,
            'fermentasi_catatan'      => $data['fermentasi_catatan'] ?? null,
            'status'                  => 'drying',
            'drying_start'            => now(),
        ]);

        return $batch->fresh();
    }

    public function selesaiDrying(int $batchId, array $data): ProcessingBatch
    {
        $batch = ProcessingBatch::findOrFail($batchId);

        if ($batch->status !== 'drying') {
            throw new \Exception('Batch ini bukan sedang berstatus drying.');
        }

        $batch->update([
            'drying_end'      => now(),
            'drying_catatan'  => $data['drying_catatan'] ?? null,
            'status'          => 'hulling',
        ]);

        return $batch->fresh();
    }

    public function selesaiHulling(int $batchId, float $qty): ProcessingBatch
    {
        if ($qty <= 0) {
            throw new \Exception('Qty hasil hulling harus lebih dari 0.');
        }

        $batch = ProcessingBatch::findOrFail($batchId);

        if ($batch->status !== 'hulling') {
            throw new \Exception('Batch ini bukan sedang berstatus hulling.');
        }
        if ($qty > (float) $batch->cherry_qty_kg) {
            throw new \Exception('Qty hasil hulling tidak boleh melebihi qty cherry awal.');
        }

        $batch->update(['hulling_qty_kg' => $qty, 'status' => 'sortir']);

        return $batch->fresh();
    }

    /** Tahap final — sortir grade baik dari defect, stok green bean masuk RST001. */
    public function selesaiSortir(int $batchId, float $qtyFinal, float $qtyDefect): ProcessingBatch
    {
        if ($qtyFinal <= 0) {
            throw new \Exception('Qty green bean final harus lebih dari 0.');
        }

        return DB::transaction(function () use ($batchId, $qtyFinal, $qtyDefect) {
            $batch = ProcessingBatch::lockForUpdate()->findOrFail($batchId);

            if ($batch->status !== 'sortir') {
                throw new \Exception('Batch ini bukan sedang berstatus sortir.');
            }
            if ($batch->hulling_qty_kg !== null && ($qtyFinal + $qtyDefect) > (float) $batch->hulling_qty_kg + 0.001) {
                throw new \Exception('Qty final + defect tidak boleh melebihi hasil hulling.');
            }

            $method = $batch->processing_method;
            $greenItem = Item::where('kode_item', $method->kodeGreenBeanArabika())->firstOrFail();

            $yield = round($qtyFinal / (float) $batch->cherry_qty_kg * 100, 2);
            $costPerKg = round((float) $batch->cherry_cost_awal / $qtyFinal, 2);

            // harga_beli_terakhir dipakai StokService::prosesTerimaTransfer() saat distribusi
            // nanti — tanpa ini green bean tiba di lokasi lain dengan cost 0.
            $greenItem->update(['harga_beli_terakhir' => $costPerKg]);

            $this->stokService->masuk(
                $greenItem->id,
                $batch->cabang_id,
                $qtyFinal,
                "Hasil processing {$batch->kode_batch} ({$method->label()})",
                'processing_batch',
                $batch->id,
                $costPerKg
            );

            $batch->update([
                'sortir_qty_kg'      => $qtyFinal,
                'sortir_defect_kg'   => $qtyDefect,
                'green_bean_item_id' => $greenItem->id,
                'yield_percent'      => $yield,
                'cost_per_kg_green'  => $costPerKg,
                'status'             => 'selesai',
                'tanggal_selesai'    => now()->toDateString(),
            ]);

            return $batch->fresh();
        });
    }

    /** Batalkan di tahap manapun — kembalikan stok cherry kalau sudah dipotong (status != draft). */
    public function batalkan(int $batchId, ?string $reason = null): ProcessingBatch
    {
        return DB::transaction(function () use ($batchId, $reason) {
            $batch = ProcessingBatch::lockForUpdate()->findOrFail($batchId);

            if (in_array($batch->status, ['selesai', 'dibatalkan'], true)) {
                throw new \Exception('Batch yang sudah selesai/dibatalkan tidak bisa dibatalkan lagi.');
            }

            if ($batch->status !== 'draft') {
                $this->stokService->masuk(
                    $batch->cherry_item_id,
                    $batch->cabang_id,
                    (float) $batch->cherry_qty_kg,
                    "Pembatalan {$batch->kode_batch} — stok cherry dikembalikan" . ($reason ? " ({$reason})" : ''),
                    'processing_batch_cancel',
                    $batch->id,
                    (float) $batch->cherry_qty_kg > 0 ? round((float) $batch->cherry_cost_awal / (float) $batch->cherry_qty_kg, 2) : 0
                );
            }

            $batch->update(['status' => 'dibatalkan', 'catatan_umum' => trim(($batch->catatan_umum ?? '') . ' | Dibatalkan: ' . ($reason ?? '-'))]);

            return $batch->fresh();
        });
    }

    private function nomorBerikutnya(): string
    {
        $prefix = 'PB-' . now()->format('Ym') . '-';
        $last = ProcessingBatch::where('kode_batch', 'like', $prefix . '%')->max('kode_batch');
        $seq = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
