<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Roastery V2 Minggu 2-3 (2026-09-27) — transaksi beli buah kopi cherry dari
 * petani. Flow TERPISAH dari Purchase Order Supplier existing (sengaja,
 * lihat FASE_ROASTERY_V2_DESIGN.md section 3) — 1 baris = 1 transaksi = 1
 * jenis buah (bukan header+items seperti PO biasa).
 */
class CherryPurchase extends Model
{
    protected $table = 'cherry_purchases';

    protected $fillable = [
        'kode_transaksi', 'tanggal', 'petani_id', 'cabang_id', 'cherry_item_id',
        'jenis_buah', 'qty_kg', 'harga_per_kg', 'qty_terima_kg', 'kualitas_grade',
        'status', 'catatan', 'user_id', 'disetujui_by', 'diterima_by',
        'disetujui_at', 'diterima_at',
    ];

    protected function casts(): array
    {
        return [
            'tanggal'       => 'date',
            'qty_kg'        => 'decimal:3',
            'harga_per_kg'  => 'decimal:2',
            'qty_terima_kg' => 'decimal:3',
            'disetujui_at'  => 'datetime',
            'diterima_at'   => 'datetime',
        ];
    }

    public function petani()      { return $this->belongsTo(Petani::class); }
    public function cabang()      { return $this->belongsTo(Cabang::class); }
    public function cherryItem()  { return $this->belongsTo(Item::class, 'cherry_item_id'); }
    public function user()        { return $this->belongsTo(User::class, 'user_id'); }
    public function disetujuiOleh() { return $this->belongsTo(User::class, 'disetujui_by'); }
    public function diterimaOleh()  { return $this->belongsTo(User::class, 'diterima_by'); }

    public function getTotalHargaAttribute(): float
    {
        return round((float) $this->qty_kg * (float) $this->harga_per_kg, 2);
    }
}
