<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Cabang;
use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\Order;
use App\Models\PengaturanUmum;
use Illuminate\Support\Collection;

/**
 * Sprint Label Cup (2026-10-19) — susun data label gelas (1 stiker = 1 gelas)
 * dari Order (takeaway/charge) atau Bill (dine-in / approve QR).
 *
 * Hanya item yang kategorinya dicentang di Cabang → Edit → Label Cup yang
 * dicetak. Baris opsi diambil dari varian (Size/Gula/Es/Susu) bila item
 * memakai varian — sehingga begitu varian menu diisi (TODO 12.6) label
 * otomatis ikut lengkap tanpa ubah kode ini.
 */
class CupLabelService
{
    public const MAKS_LABEL = 100;

    public function untukOrder(Order $order): array
    {
        $order->loadMissing(['cabang', 'items.item.category', 'pelanggan']);
        $cabang = $order->cabang;

        $nomor = $order->nomor_antrian
            ? str_pad((string) $order->nomor_antrian, 3, '0', STR_PAD_LEFT)
            : $this->nomorPendek($order->nomor_order);

        $tipe = match ($order->tipe_transaksi?->value) {
            'dine_in'  => 'Dine-in' . ($order->nomor_meja ? ' · Meja ' . $order->nomor_meja : ''),
            'takeaway' => 'Take Away',
            default    => $order->tipe_transaksi?->label() ?? 'Take Away',
        };

        $rows = $order->items->map(fn ($oi) => [
            'item'       => $oi->item,
            'nama'       => $oi->item?->nama_item ?? $oi->nama_item,
            'variant_id' => $oi->item_variant_id,
            'qty'        => (float) $oi->qty,
            'catatan'    => $oi->catatan,
        ]);

        return $this->susun($cabang, $rows, [
            'nomor'     => $nomor,
            'pelanggan' => $order->nama_pelanggan ?: $order->pelanggan?->nama_pelanggan,
            'tipe'      => $tipe,
            'jam'       => ($order->created_at ?? now())->format('H:i'),
            'catatan_order' => $order->catatan,
        ]);
    }

    /**
     * @param array|null $billItemIds  null = semua item bill; isi = hanya item tertentu (mis. item yang baru masuk)
     */
    public function untukBill(Bill $bill, ?array $billItemIds = null): array
    {
        $bill->loadMissing(['cabang', 'meja', 'items.item.category']);

        $items = $bill->items;
        if ($billItemIds !== null) {
            $ids = array_map('intval', $billItemIds);
            $items = $items->filter(fn ($bi) => in_array((int) $bi->id, $ids, true));
        }

        $rows = $items->map(fn ($bi) => [
            'item'       => $bi->item,
            'nama'       => $bi->item?->nama_item ?? '-',
            'variant_id' => is_array($bi->varian) ? ($bi->varian['item_variant_id'] ?? null) : null,
            'qty'        => (float) $bi->qty,
            'catatan'    => $bi->catatan,
        ]);

        return $this->susun($bill->cabang, $rows, [
            'nomor'     => $this->nomorPendek($bill->nomor_bill),
            'pelanggan' => $bill->customer_name,
            'tipe'      => 'Dine-in' . ($bill->meja ? ' · ' . $bill->meja->nama_meja : ''),
            'jam'       => now()->format('H:i'),
            'catatan_order' => null,
        ]);
    }

    /** Jumlah stiker yang akan dicetak (0 = tidak perlu tombol label). */
    public function jumlah(array $hasil): int
    {
        return count($hasil['labels']);
    }

    private function susun(?Cabang $cabang, Collection $rows, array $info): array
    {
        $aktif = (bool) ($cabang?->label_cup_aktif ?? false);
        $kategori = $cabang ? $cabang->kategoriLabelCup() : [];

        $layak = $rows->filter(function ($r) use ($kategori) {
            /** @var Item|null $item */
            $item = $r['item'];

            return $item && in_array($item->category?->kode_kategori, $kategori, true);
        })->values();

        $gelasTotal = (int) $layak->sum(fn ($r) => $this->jumlahGelas($r['qty']));
        $gelasTotal = min($gelasTotal, self::MAKS_LABEL);

        $labels = [];
        $ke = 0;
        foreach ($layak as $r) {
            $opsi = $this->opsiVarian($r['variant_id']);
            for ($i = 0; $i < $this->jumlahGelas($r['qty']) && $ke < $gelasTotal; $i++) {
                $ke++;
                $labels[] = [
                    'toko'      => $this->namaToko(),
                    'nomor'     => $info['nomor'],
                    'ke'        => $ke,
                    'dari'      => $gelasTotal,
                    'nama'      => mb_strtoupper($r['nama']),
                    'opsi'      => $opsi,
                    'catatan'   => $r['catatan'] ? trim($r['catatan']) : null,
                    'pelanggan' => $info['pelanggan'] ? trim($info['pelanggan']) : null,
                    'info'      => $info['tipe'] . ' · ' . $info['jam'],
                ];
            }
        }

        return [
            'aktif'  => $aktif,
            'ukuran' => [
                'lebar'  => (int) ($cabang?->label_cup_lebar_mm ?: 50),
                'tinggi' => (int) ($cabang?->label_cup_tinggi_mm ?: 30),
            ],
            'labels' => $aktif ? $labels : [],
        ];
    }

    private function jumlahGelas(float $qty): int
    {
        return max(1, (int) round($qty));
    }

    /** Nilai varian sebagai baris opsi, mis. ["Size: Large", "Gula: Less"]. */
    private function opsiVarian($variantId): array
    {
        if (!$variantId) {
            return [];
        }
        $variant = ItemVariant::with('attributeValues.attribute')->find((int) $variantId);
        if (!$variant) {
            return [];
        }

        return $variant->attributeValues
            ->map(fn ($v) => ($v->attribute?->nama ? $v->attribute->nama . ': ' : '') . $v->nilai)
            ->values()->all();
    }

    private function nomorPendek(?string $nomor): string
    {
        if (!$nomor) {
            return '-';
        }
        // Ambil segmen angka terakhir (mis. ORD-20261019-0025 → 0025)
        return preg_match('/(\d+)\s*$/', $nomor, $m) ? $m[1] : $nomor;
    }

    private function namaToko(): string
    {
        static $nama = null;
        if ($nama === null) {
            try {
                $nama = PengaturanUmum::getSetting()->nama_perusahaan ?: 'Kopi Drip';
            } catch (\Throwable) {
                $nama = 'Kopi Drip';
            }
        }

        return $nama;
    }
}
