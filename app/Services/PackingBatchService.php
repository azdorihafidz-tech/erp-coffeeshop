<?php

namespace App\Services;

use App\Enums\PackSize;
use App\Models\Item;
use App\Models\PackingBatch;
use Illuminate\Support\Facades\DB;

/**
 * Roastery V2 Minggu 5-6 — kemas roasted whole bean atau ground jadi pack
 * retail 250g/500g/1kg. Semua mutasi stok lewat StokService existing
 * (CLAUDE.md 3.3). Cost per pack = cost/kg sumber (FIFO) x berat pack +
 * biaya packaging default (kemasan+label, keputusan default Rp2.000/pack —
 * bisa disesuaikan Owner nanti, belum ada UI setting-nya).
 */
class PackingBatchService
{
    private const BIAYA_PACKAGING_DEFAULT = 2000.0;

    /** source item kode -> [pack size => target item kode] (ItemPackSeeder). */
    private const TARGET_MAP = [
        'RTB-CURAH-ARABIKA-MEDIUM' => [
            '250g' => 'RTBPACK-WHOLE-MEDIUM-250g',
            '500g' => 'RTBPACK-WHOLE-MEDIUM-500g',
            '1kg'  => 'RTBPACK-WHOLE-MEDIUM-1kg',
        ],
        'GRD-ARB-MEDIUM-M' => [
            '250g' => 'RTBPACK-GROUND-MEDIUM-250g',
            '500g' => 'RTBPACK-GROUND-MEDIUM-500g',
            '1kg'  => 'RTBPACK-GROUND-MEDIUM-1kg',
        ],
        'GRD-ARB-MEDIUM-F' => [
            '250g' => 'RTBPACK-GROUND-FINE-250g',
            '500g' => 'RTBPACK-GROUND-FINE-500g',
            '1kg'  => 'RTBPACK-GROUND-FINE-1kg',
        ],
        'GRD-ARB-MEDIUM-XF' => [
            '250g' => 'RTBPACK-GROUND-XFINE-250g',
            '500g' => 'RTBPACK-GROUND-XFINE-500g',
            '1kg'  => 'RTBPACK-GROUND-XFINE-1kg',
        ],
    ];

    public function __construct(private StokService $stokService) {}

    public function startBatch(array $data, int $userId): PackingBatch
    {
        $item = Item::findOrFail($data['source_item_id']);
        $qty = (float) $data['source_qty_kg_in'];

        if ($qty <= 0) {
            throw new \Exception('Qty sumber harus lebih dari 0.');
        }

        $cabangId = RoastingBatchService::roasteryCabangId();
        $stokTersedia = $item->stokDiLokasi($cabangId);
        if ($stokTersedia < $qty) {
            throw new \Exception("Stok '{$item->nama_item}' tidak mencukupi. Tersedia: {$stokTersedia} kg, dibutuhkan: {$qty} kg.");
        }

        if (! isset(self::TARGET_MAP[$item->kode_item])) {
            throw new \Exception("Item '{$item->nama_item}' belum punya pemetaan item pack (lihat ItemPackSeeder).");
        }

        return PackingBatch::create([
            'kode_batch'        => $this->nomorBerikutnya(),
            'tanggal'           => $data['tanggal'],
            'cabang_id'         => $cabangId,
            'user_id'           => $userId,
            'source_type'       => $data['source_type'],
            'source_item_id'    => $item->id,
            'source_qty_kg_in'  => $qty,
            'target_pack_size'  => $data['target_pack_size'],
            'catatan'           => $data['catatan'] ?? null,
            'status'            => 'draft',
        ]);
    }

    /** Potong stok sumber (FIFO), tambah stok pack, hitung cost per pack. */
    public function completeBatch(int $batchId, int $qtyPack): PackingBatch
    {
        if ($qtyPack <= 0) {
            throw new \Exception('Qty pack hasil harus lebih dari 0.');
        }

        return DB::transaction(function () use ($batchId, $qtyPack) {
            $batch = PackingBatch::with('sourceItem')->lockForUpdate()->findOrFail($batchId);

            if ($batch->status !== 'draft') {
                throw new \Exception('Batch ini sudah diproses (bukan draft lagi).');
            }

            $packSize = PackSize::from($batch->target_pack_size->value);
            $beratDipakai = round($qtyPack * $packSize->kg(), 3);

            if ($beratDipakai > (float) $batch->source_qty_kg_in + 0.0005) {
                throw new \Exception("Total berat pack ({$beratDipakai} kg) melebihi qty sumber masuk ({$batch->source_qty_kg_in} kg).");
            }

            $targetKode = self::TARGET_MAP[$batch->sourceItem->kode_item][$batch->target_pack_size->value];
            $targetItem = Item::where('kode_item', $targetKode)->firstOrFail();

            $costTotal = $this->stokService->keluar(
                $batch->source_item_id,
                $batch->cabang_id,
                $beratDipakai,
                "Packing {$batch->kode_batch} — {$packSize->label()}",
                'packing_batch',
                $batch->id
            );

            $costPerKgSumber = $beratDipakai > 0 ? $costTotal / $beratDipakai : 0.0;
            $costPerPack = round($costPerKgSumber * $packSize->kg() + self::BIAYA_PACKAGING_DEFAULT, 2);

            $this->stokService->masuk(
                $targetItem->id,
                $batch->cabang_id,
                (float) $qtyPack,
                "Packing {$batch->kode_batch} — hasil kemas {$packSize->label()}",
                'packing_batch',
                $batch->id,
                $costPerPack
            );

            if ($costPerPack > 0) {
                $targetItem->update(['harga_beli_terakhir' => $costPerPack]);
            }

            $batch->update([
                'target_item_id'  => $targetItem->id,
                'target_qty_pack' => $qtyPack,
                'waste_kg'        => round((float) $batch->source_qty_kg_in - $beratDipakai, 3),
                'cost_per_pack'   => $costPerPack,
                'status'          => 'selesai',
            ]);

            return $batch->fresh();
        });
    }

    public function batalkan(int $batchId): PackingBatch
    {
        $batch = PackingBatch::findOrFail($batchId);

        if ($batch->status !== 'draft') {
            throw new \Exception('Hanya batch draft yang bisa dibatalkan (stok batch selesai sudah bergerak).');
        }

        $batch->update(['status' => 'dibatalkan']);

        return $batch->fresh();
    }

    private function nomorBerikutnya(): string
    {
        $prefix = 'PK-' . now()->format('Ym') . '-';
        $last = PackingBatch::where('kode_batch', 'like', $prefix . '%')->max('kode_batch');
        $seq = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
