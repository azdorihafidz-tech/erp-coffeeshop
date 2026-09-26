<?php

namespace App\Services;

use App\Models\Cabang;
use App\Models\CherryPurchase;
use Illuminate\Support\Facades\DB;

/**
 * Roastery V2 Minggu 2-3 — beli buah kopi cherry dari petani. Alur:
 * draft → disetujui → diterima (stok cherry masuk RST001 via StokService,
 * FIFO+movements existing, CLAUDE.md 3.3) — atau draft/disetujui → dibatalkan.
 */
class CherryPurchaseService
{
    public function __construct(private StokService $stokService) {}

    public static function roasteryCabangId(): int
    {
        return (int) Cabang::where('kode_cabang', 'RST001')->value('id');
    }

    public function createDraft(array $data, int $userId): CherryPurchase
    {
        if ((float) $data['qty_kg'] <= 0) {
            throw new \Exception('Qty buah harus lebih dari 0.');
        }

        return CherryPurchase::create([
            'kode_transaksi' => $this->nomorBerikutnya(),
            'tanggal'        => $data['tanggal'],
            'petani_id'      => $data['petani_id'],
            'cabang_id'      => self::roasteryCabangId(),
            'cherry_item_id' => $data['cherry_item_id'],
            'jenis_buah'     => $data['jenis_buah'],
            'qty_kg'         => $data['qty_kg'],
            'harga_per_kg'   => $data['harga_per_kg'],
            'kualitas_grade' => $data['kualitas_grade'] ?? null,
            'catatan'        => $data['catatan'] ?? null,
            'status'         => 'draft',
            'user_id'        => $userId,
        ]);
    }

    public function setujui(int $id, int $userId): CherryPurchase
    {
        $cp = CherryPurchase::findOrFail($id);

        if ($cp->status !== 'draft') {
            throw new \Exception('Hanya transaksi berstatus draft yang bisa disetujui.');
        }

        $cp->update(['status' => 'disetujui', 'disetujui_by' => $userId, 'disetujui_at' => now()]);

        return $cp->fresh();
    }

    /** Terima cherry — qty_terima_kg boleh beda dari qty_kg (susut jalan). Stok masuk RST001. */
    public function terima(int $id, float $qtyTerimaKg, int $userId): CherryPurchase
    {
        if ($qtyTerimaKg <= 0) {
            throw new \Exception('Qty terima harus lebih dari 0.');
        }

        return DB::transaction(function () use ($id, $qtyTerimaKg, $userId) {
            $cp = CherryPurchase::lockForUpdate()->findOrFail($id);

            if ($cp->status !== 'disetujui') {
                throw new \Exception('Hanya transaksi berstatus disetujui yang bisa diterima.');
            }

            $this->stokService->masuk(
                $cp->cherry_item_id,
                $cp->cabang_id,
                $qtyTerimaKg,
                "Terima buah cherry dari {$cp->petani->nama} — {$cp->kode_transaksi}",
                'cherry_purchase',
                $cp->id,
                (float) $cp->harga_per_kg
            );

            $cp->update([
                'status'       => 'diterima',
                'qty_terima_kg'=> $qtyTerimaKg,
                'diterima_by'  => $userId,
                'diterima_at'  => now(),
            ]);

            return $cp->fresh();
        });
    }

    public function batalkan(int $id): CherryPurchase
    {
        $cp = CherryPurchase::findOrFail($id);

        if (! in_array($cp->status, ['draft', 'disetujui'], true)) {
            throw new \Exception('Hanya transaksi draft/disetujui yang bisa dibatalkan (stok yang sudah diterima tidak bisa dibatalkan lewat aksi ini).');
        }

        $cp->update(['status' => 'dibatalkan']);

        return $cp->fresh();
    }

    private function nomorBerikutnya(): string
    {
        $prefix = 'BC-' . now()->format('Ym') . '-';
        $last = CherryPurchase::where('kode_transaksi', 'like', $prefix . '%')->max('kode_transaksi');
        $seq = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
