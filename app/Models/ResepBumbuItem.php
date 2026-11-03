<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResepBumbuItem extends Model
{
    protected $fillable = [
        'resep_bumbu_id',
        'item_id',
        // Fitur "Import dari Bumbu Pusat" (2026-09-17) — mutually exclusive
        // dengan item_id: baris ini item_id ATAU resep_bumbu_ref_id, tidak
        // dua-duanya/kosong dua-duanya (divalidasi di controller, bukan DB
        // constraint). Lihat isLinked().
        'resep_bumbu_ref_id',
        'qty_per_unit',
        'satuan',
        'is_wajib',
        'mode_harga',
        'urutan',
    ];

    protected function casts(): array
    {
        return [
            'qty_per_unit' => 'decimal:3',
            'is_wajib'   => 'boolean',
        ];
    }

    public function resepBumbu()
    {
        return $this->belongsTo(ResepBumbu::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    /** Master Bumbu Pusat yang di-link (Fitur Import Bumbu Pusat, 2026-09-17). */
    public function resepBumbuRef()
    {
        return $this->belongsTo(ResepBumbu::class, 'resep_bumbu_ref_id');
    }

    public function isLinked(): bool
    {
        return $this->resep_bumbu_ref_id !== null;
    }

    /**
     * Expand baris linked jadi daftar kebutuhan bahan MENTAH (item_id
     * langsung), sudah di-skala oleh qty_per_unit_dalam_kg baris ini
     * (multiplier "berapa porsi bumbu per 1 unit produk"). Baris non-linked
     * (item_id langsung) kembalikan dirinya sendiri sebagai 1 elemen.
     * Anti cyclic-reference BY CONSTRUCTION: item milik Master Bumbu Pusat
     * (resepBumbuRef->items) TIDAK PERNAH linked lagi (lihat migration +
     * MasterResepBumbuController::storeItem yang tidak terima resep_bumbu_ref_id),
     * jadi expand ini SELALU cuma 1 level, tidak perlu rekursi/deteksi cycle.
     *
     * @return array<int, array{item_id:int, butuh_kg:float}>
     */
    public function expandKeBahanMentah(): array
    {
        if (! $this->isLinked()) {
            return $this->item_id ? [['item_id' => $this->item_id, 'butuh_kg' => $this->qty_per_unit_dalam_kg]] : [];
        }

        $bumbu = $this->resepBumbuRef ?? $this->resepBumbuRef()->first();
        if (! $bumbu) return [];

        $hasil = [];
        foreach ($bumbu->items as $innerItem) {
            if (! $innerItem->item_id) continue; // safety: skip kalau ada data korup
            $hasil[] = [
                'item_id'  => $innerItem->item_id,
                'butuh_kg' => $innerItem->qty_per_unit_dalam_kg * $this->qty_per_unit_dalam_kg,
            ];
        }
        return $hasil;
    }

    /**
     * Konversi qty_per_unit ke kg sesuai satuan takaran resep (g/gram/ons/ml).
     * Satu-satunya sumber konversi — dipakai juga oleh endpoint AJAX POS
     * (PenjualanController::resepBumbuItems) supaya tidak ada logic ganda.
     */
    public function getQtyPerUnitDalamKgAttribute(): float
    {
        // Baris linked (Bumbu Pusat) tidak punya item — satuannya 'porsi', apa adanya.
        $satuanStok = $this->item_id ? $this->item?->satuan : null;

        return self::konversiKeSatuanStok((float) $this->qty_per_unit, $this->satuan, $satuanStok);
    }

    /** Faktor ke satuan dasar (gram / ml) per kelompok satuan. */
    private const FAKTOR_SATUAN = [
        'massa'  => ['mg' => 0.001, 'g' => 1, 'gr' => 1, 'gram' => 1, 'ons' => 100, 'kg' => 1000, 'kilogram' => 1000],
        'volume' => ['ml' => 1, 'cc' => 1, 'l' => 1000, 'lt' => 1000, 'ltr' => 1000, 'liter' => 1000],
    ];

    /**
     * Aturan konversi takaran resep → satuan stok bahan. Static supaya
     * kalkulator HPP form Produk Jual (baris dari isi form, belum tersimpan)
     * memakai aturan yang SAMA dengan pemotongan stok di POS.
     *
     * 2026-11-03: dulu takaran gram/ml SELALU ÷1000 (anggapan stok bahan kg/liter)
     * → bahan yang stoknya dicatat per gram ikut ÷1000 (subtotal Rp0, stok
     * terpotong 1/1000). Sekarang kalau takaran & satuan stok sekelompok
     * (massa/volume) dihitung sesuai faktornya (10 g dari stok gram = 10,
     * dari stok kg = 0,01). Satuan stok tak dikenal/beda kelompok → aturan
     * lama (g/ml ÷1000, ons ÷10) supaya resep lama tidak berubah.
     */
    public static function konversiKeSatuanStok(float $qty, ?string $satuan, ?string $satuanStok = null): float
    {
        $takaran = strtolower(trim((string) $satuan));
        $stok = strtolower(trim((string) $satuanStok));

        foreach (self::FAKTOR_SATUAN as $faktor) {
            if (isset($faktor[$takaran], $faktor[$stok])) {
                return $qty * $faktor[$takaran] / $faktor[$stok];
            }
        }

        return match ($takaran) {
            'g', 'gr', 'gram', 'ml' => $qty / 1000,
            'ons'                   => $qty / 10,
            default                 => $qty, // 'kg', 'liter', 'pcs', atau satuan tak dikenal
        };
    }

    /**
     * Preview harga (display-only, TIDAK disimpan ke DB) — total biaya bahan
     * ini per 1 unit produksi.
     * Sprint Fix (2026-10-02): sumber harga = harga_beli_terakhir (MODAL),
     * bukan harga_jual — Master Bumbu Pusat itung biaya produksi, bukan
     * harga jual ke customer. Lihat CLAUDE.md riwayat "Sprint Fix Harga
     * Master Bumbu Pusat".
     * 2026-10-30: mode_harga (gratis/pakai_master, warisan jasa giling) tidak
     * dipakai lagi — semua bahan selalu dihitung sebagai modal.
     */
    public function getTotalHargaMasterAttribute(): float
    {
        return round($this->qty_per_unit_dalam_kg * (float) ($this->item?->harga_beli_terakhir ?? 0), 2);
    }
}
