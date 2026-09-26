<?php

namespace App\Services;

use App\Enums\GrindSize;
use App\Models\GrindingBatch;
use App\Models\Item;
use Illuminate\Support\Facades\DB;

/**
 * Roastery V2 Minggu 4-5 — giling roasted whole bean jadi ground per grind
 * size. Semua mutasi stok lewat StokService existing (CLAUDE.md 3.3).
 */
class GrindingBatchService
{
    public function __construct(private StokService $stokService) {}

    /** Map roasted item + grind size -> item ground yang sesuai (ItemGroundSeeder). */
    private const GROUND_MAP = [
        'medium'     => 'GRD-ARB-MEDIUM-M',
        'fine'       => 'GRD-ARB-MEDIUM-F',
        'extra_fine' => 'GRD-ARB-MEDIUM-XF',
    ];

    public function startBatch(array $data, int $userId): GrindingBatch
    {
        $item = Item::findOrFail($data['roasted_item_id']);
        $qty = (float) $data['roasted_qty_kg_in'];

        if ($qty <= 0) {
            throw new \Exception('Qty roasted bean harus lebih dari 0.');
        }

        $cabangId = \App\Services\RoastingBatchService::roasteryCabangId();
        $stokTersedia = $item->stokDiLokasi($cabangId);
        if ($stokTersedia < $qty) {
            throw new \Exception("Stok roasted '{$item->nama_item}' tidak mencukupi. Tersedia: {$stokTersedia} kg, dibutuhkan: {$qty} kg.");
        }

        if (! isset(self::GROUND_MAP[$data['grind_size']])) {
            throw new \Exception('Grind size ini belum punya item ground yang tersedia (baru mendukung Medium/Fine/Extra Fine).');
        }

        return GrindingBatch::create([
            'kode_batch'         => $this->nomorBerikutnya(),
            'tanggal'            => $data['tanggal'],
            'cabang_id'          => $cabangId,
            'user_id'            => $userId,
            'roasted_item_id'    => $item->id,
            'roasted_qty_kg_in'  => $qty,
            'grind_size'         => $data['grind_size'],
            'catatan'            => $data['catatan'] ?? null,
            'status'             => 'draft',
        ]);
    }

    /** Potong roasted whole bean, tambah stok ground (FIFO), hitung cost/kg + waste. */
    public function completeBatch(int $batchId, float $groundQtyOut): GrindingBatch
    {
        if ($groundQtyOut <= 0) {
            throw new \Exception('Qty hasil giling harus lebih dari 0.');
        }

        return DB::transaction(function () use ($batchId, $groundQtyOut) {
            $batch = GrindingBatch::lockForUpdate()->findOrFail($batchId);

            if ($batch->status !== 'draft') {
                throw new \Exception('Batch ini sudah diproses (bukan draft lagi).');
            }
            if ($groundQtyOut > (float) $batch->roasted_qty_kg_in) {
                throw new \Exception('Qty hasil giling tidak boleh melebihi qty roasted bean masuk.');
            }

            $groundItem = Item::where('kode_item', self::GROUND_MAP[$batch->grind_size->value])->firstOrFail();

            $costAwal = $this->stokService->keluar(
                $batch->roasted_item_id,
                $batch->cabang_id,
                (float) $batch->roasted_qty_kg_in,
                "Grinding {$batch->kode_batch} — roasted bean masuk penggilingan",
                'grinding_batch',
                $batch->id
            );

            $costPerKg = round($costAwal / $groundQtyOut, 2);

            $this->stokService->masuk(
                $groundItem->id,
                $batch->cabang_id,
                $groundQtyOut,
                "Grinding {$batch->kode_batch} — hasil giling",
                'grinding_batch',
                $batch->id,
                $costPerKg
            );

            if ($costPerKg > 0) {
                $groundItem->update(['harga_beli_terakhir' => $costPerKg]);
            }

            $batch->update([
                'ground_item_id'     => $groundItem->id,
                'ground_qty_kg_out'  => $groundQtyOut,
                'waste_kg'           => round((float) $batch->roasted_qty_kg_in - $groundQtyOut, 3),
                'cost_per_kg'        => $costPerKg,
                'status'             => 'selesai',
            ]);

            return $batch->fresh();
        });
    }

    public function batalkan(int $batchId): GrindingBatch
    {
        $batch = GrindingBatch::findOrFail($batchId);

        if ($batch->status !== 'draft') {
            throw new \Exception('Hanya batch draft yang bisa dibatalkan (stok batch selesai sudah bergerak).');
        }

        $batch->update(['status' => 'dibatalkan']);

        return $batch->fresh();
    }

    private function nomorBerikutnya(): string
    {
        $prefix = 'GB-' . now()->format('Ym') . '-';
        $last = GrindingBatch::where('kode_batch', 'like', $prefix . '%')->max('kode_batch');
        $seq = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
