<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Resep Produksi bahan setengah jadi (mis. Saus Padang, Cup Kopi Isi).
 * Terpisah dari ResepBumbu (komposisi Produk Jual) — lihat migration
 * 2026_11_06_100001_create_produksi_tables.
 */
class ResepProduksi extends Model
{
    protected $table = 'resep_produksi';

    protected $fillable = ['item_id', 'qty_hasil', 'catatan', 'is_active', 'created_by'];

    protected function casts(): array
    {
        return ['qty_hasil' => 'decimal:3', 'is_active' => 'boolean'];
    }

    public function item()  { return $this->belongsTo(Item::class); }
    public function items() { return $this->hasMany(ResepProduksiItem::class)->orderBy('urutan')->orderBy('id'); }

    /**
     * Kebutuhan bahan (dalam satuan stok masing-masing bahan) untuk membuat
     * $qtyRencana satuan barang hasil.
     *
     * @return array<int, float> item_id => qty satuan stok
     */
    public function kebutuhan(float $qtyRencana): array
    {
        $faktor = (float) $this->qty_hasil > 0 ? $qtyRencana / (float) $this->qty_hasil : 0.0;
        $hasil = [];
        foreach ($this->items as $baris) {
            $qtyStok = ResepBumbuItem::konversiKeSatuanStok((float) $baris->qty, $baris->satuan, $baris->bahan?->satuan) * $faktor;
            $hasil[$baris->item_id] = ($hasil[$baris->item_id] ?? 0) + $qtyStok;
        }

        return array_map(fn ($q) => round($q, 3), $hasil);
    }
}
