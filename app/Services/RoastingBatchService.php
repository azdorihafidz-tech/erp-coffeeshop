<?php

namespace App\Services;

use App\Models\Cabang;
use App\Models\Item;
use App\Models\RoastingBatch;
use App\Models\RoastingBatchPack;
use Illuminate\Support\Facades\DB;

/**
 * Roastery R2 — batch roasting di Gudang Pusat (GP001). Semua mutasi stok
 * lewat StokService (FIFO + stock_movements) — TIDAK ada logic stok baru
 * (CLAUDE.md 3.3). Cost = harga green bean saja (MVP, keputusan Owner Q4).
 */
class RoastingBatchService
{
    public function __construct(private StokService $stokService) {}

    /**
     * BUG DITEMUKAN saat integrasi Roastery V2 Minggu 4-5 (2026-09-29):
     * batch roasting V1 dibuat di GP001, sementara buah/cherry processing V2
     * (CherryPurchase, ProcessingBatch) semuanya di RST001 (cabang tipe
     * "roastery" khusus, dibuat Minggu 1-2) — 2 lokasi berbeda, sehingga
     * green bean hasil in-house processing tidak pernah "ketemu" alur
     * roasting tanpa transfer manual. FIX: batch BARU dibuat di RST001
     * (unit roastery yang benar sesuai desain V2 — RST001 = "alat & tempat
     * siap", Q7). Data historis roasting_batches di GP001 TIDAK diubah
     * (kolom cabang_id tersimpan per baris, riwayat tetap valid).
     */
    public static function roasteryCabangId(): int
    {
        return (int) Cabang::where('kode_cabang', 'RST001')->value('id');
    }

    /**
     * $data['green_bean_source'] = 'in_house' | 'bought' | 'seed' (default 'seed'
     * — batch lama/manual tanpa link sumber, sama seperti perilaku V1).
     * 'in_house' -> isi 'processing_batch_id' (harus berstatus selesai).
     * 'bought'   -> isi 'pembelian_id' (opsional, cuma label/link, tidak divalidasi status).
     */
    public function createBatch(array $data, int $userId): RoastingBatch
    {
        $green  = (float) $data['green_qty_kg'];
        $roast  = (float) $data['roasted_qty_kg'];
        $source = $data['green_bean_source'] ?? 'seed';

        if ($green <= 0) {
            throw new \Exception('Berat green bean harus lebih dari 0.');
        }
        if ($roast < 0 || $roast > $green) {
            throw new \Exception('Berat roasted tidak boleh negatif atau melebihi berat green bean.');
        }

        if ($source === 'in_house' && ! empty($data['processing_batch_id'])) {
            $pb = \App\Models\ProcessingBatch::find($data['processing_batch_id']);
            if (! $pb || $pb->status !== 'selesai') {
                throw new \Exception('Processing batch yang dipilih belum berstatus selesai.');
            }
            if ($pb->green_bean_item_id != $data['green_bean_item_id']) {
                throw new \Exception('Item green bean tidak cocok dengan hasil processing batch yang dipilih.');
            }
        }

        return DB::transaction(function () use ($data, $userId, $green, $roast, $source) {
            return RoastingBatch::create([
                'nomor_batch'           => $this->nomorBatchBerikutnya(),
                'tanggal'               => $data['tanggal'],
                'cabang_id'             => self::roasteryCabangId(),
                'user_id'               => $userId,
                'profile_id'            => $data['profile_id'],
                'green_bean_item_id'    => $data['green_bean_item_id'],
                'green_bean_source'     => $source,
                'processing_batch_id'   => $source === 'in_house' ? ($data['processing_batch_id'] ?? null) : null,
                'pembelian_id'          => $source === 'bought' ? ($data['pembelian_id'] ?? null) : null,
                'green_qty_kg'          => $green,
                'roasted_curah_item_id' => $data['roasted_curah_item_id'],
                'roasted_qty_kg'        => $roast,
                'waste_qty_kg'          => round($green - $roast, 3),
                'yield_rate_percent'    => round($roast / $green * 100, 2),
                'catatan'               => $data['catatan'] ?? null,
                'status'                => 'draft',
            ]);
        });
    }

    /** Kurangi stok green, tambah stok roasted curah (FIFO), hitung cost/kg, tandai completed. */
    public function completeBatch(int $batchId): RoastingBatch
    {
        return DB::transaction(function () use ($batchId) {
            $batch = RoastingBatch::with(['greenBean', 'roastedCurah'])->lockForUpdate()->findOrFail($batchId);

            if ($batch->status !== 'draft') {
                throw new \Exception('Batch ini sudah diproses (bukan draft lagi).');
            }

            $ref = 'roasting_batch';
            $costAwal = $this->stokService->keluar(
                $batch->green_bean_item_id,
                $batch->cabang_id,
                (float) $batch->green_qty_kg,
                "Roasting {$batch->nomor_batch} — green bean masuk roaster",
                $ref,
                $batch->id
            );

            $roasted = (float) $batch->roasted_qty_kg;
            $costPerKg = $roasted > 0 ? round($costAwal / $roasted, 2) : 0.0;

            if ($roasted > 0) {
                $this->stokService->masuk(
                    $batch->roasted_curah_item_id,
                    $batch->cabang_id,
                    $roasted,
                    "Roasting {$batch->nomor_batch} — hasil roasting (curah)",
                    $ref,
                    $batch->id,
                    $costPerKg
                );
            }

            // harga_beli_terakhir dipakai StokService::prosesTerimaTransfer() sbg harga batch di outlet
            // tujuan — tanpa ini roasted curah tiba di outlet dengan cost 0 dan HPP kopi jadi 0.
            if ($costPerKg > 0) {
                $batch->roastedCurah()->update(['harga_beli_terakhir' => $costPerKg]);
            }

            $batch->update([
                'status'              => 'completed',
                'cost_awal'           => round($costAwal, 2),
                'cost_per_kg_roasted' => $costPerKg,
                'completed_at'        => now(),
            ]);

            return $batch->fresh();
        });
    }

    public function cancelBatch(int $batchId): RoastingBatch
    {
        $batch = RoastingBatch::findOrFail($batchId);

        if ($batch->status !== 'draft') {
            throw new \Exception('Hanya batch draft yang bisa dibatalkan (stok batch completed sudah bergerak).');
        }

        $batch->update(['status' => 'cancelled']);

        return $batch->fresh();
    }

    /**
     * Pengemasan: kurangi roasted curah, tambah stok RTB pack di GP001.
     * $packItems = [['item_pack_id' => int, 'qty_pack' => int, 'berat_per_pack_gr' => int], ...]
     */
    public function packBatch(int $batchId, array $packItems, int $userId): RoastingBatch
    {
        return DB::transaction(function () use ($batchId, $packItems, $userId) {
            $batch = RoastingBatch::with('packs')->lockForUpdate()->findOrFail($batchId);

            if ($batch->status !== 'completed') {
                throw new \Exception('Pengemasan hanya untuk batch yang sudah completed.');
            }

            $packItems = array_values(array_filter($packItems, fn ($p) => (int) ($p['qty_pack'] ?? 0) > 0));
            if (empty($packItems)) {
                throw new \Exception('Isi minimal 1 baris pengemasan dengan jumlah pack > 0.');
            }

            $totalKg = 0.0;
            foreach ($packItems as $p) {
                $totalKg += (int) $p['qty_pack'] * (int) $p['berat_per_pack_gr'] / 1000;
            }
            $totalKg = round($totalKg, 3);

            $sisa = $batch->sisa_curah_kg;
            if ($totalKg > $sisa + 0.0005) {
                throw new \Exception("Berat yang dikemas ({$totalKg} kg) melebihi sisa curah batch ini ({$sisa} kg).");
            }

            $this->stokService->keluar(
                $batch->roasted_curah_item_id,
                $batch->cabang_id,
                $totalKg,
                "Pengemasan dari {$batch->nomor_batch}",
                'roasting_batch_pack',
                $batch->id
            );

            foreach ($packItems as $p) {
                $qty   = (int) $p['qty_pack'];
                $gram  = (int) $p['berat_per_pack_gr'];
                $kg    = round($qty * $gram / 1000, 3);
                $cost  = round((float) $batch->cost_per_kg_roasted * $gram / 1000, 2);

                RoastingBatchPack::create([
                    'batch_id'          => $batch->id,
                    'item_pack_id'      => $p['item_pack_id'],
                    'qty_pack'          => $qty,
                    'berat_per_pack_gr' => $gram,
                    'total_berat_kg'    => $kg,
                    'cost_per_pack'     => $cost,
                    'user_id'           => $userId,
                ]);

                if ($cost > 0) {
                    Item::where('id', $p['item_pack_id'])->update(['harga_beli_terakhir' => $cost]);
                }

                $this->stokService->masuk(
                    (int) $p['item_pack_id'],
                    $batch->cabang_id,
                    (float) $qty,
                    "Pengemasan dari {$batch->nomor_batch} ({$gram} g)",
                    'roasting_batch_pack',
                    $batch->id,
                    $cost
                );
            }

            return $batch->fresh('packs');
        });
    }

    private function nomorBatchBerikutnya(): string
    {
        $prefix = 'RB-' . now()->format('Y') . '-';
        $last = RoastingBatch::where('nomor_batch', 'like', $prefix . '%')->max('nomor_batch');
        $seq = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
