<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Rincian barang Kirim ke Cabang per baris order (lihat migration 2026_11_06_200001). */
class OrderKirimanItem extends Model
{
    protected $fillable = ['order_id', 'order_item_id', 'item_id', 'qty_kirim', 'hpp_kirim', 'qty_terima', 'nilai_terima', 'qty_retur'];

    protected function casts(): array
    {
        return [
            'qty_kirim'    => 'decimal:3',
            'hpp_kirim'    => 'decimal:2',
            'qty_terima'   => 'decimal:3',
            'nilai_terima' => 'decimal:2',
            'qty_retur'    => 'decimal:3',
        ];
    }

    public function order()     { return $this->belongsTo(Order::class); }
    public function orderItem() { return $this->belongsTo(OrderItem::class); }
    public function item()      { return $this->belongsTo(Item::class); }

    /** Modal penjual per unit (dipakai saat kiriman kurang, ditolak, atau retur dengan uang kembali). */
    public function getHppPerUnitAttribute(): float
    {
        return (float) $this->qty_kirim > 0 ? round((float) $this->hpp_kirim / (float) $this->qty_kirim, 2) : 0.0;
    }

    /** Harga yang dibayar cabang per unit. */
    public function getHargaTerimaPerUnitAttribute(): float
    {
        return (float) $this->qty_terima > 0 ? round((float) $this->nilai_terima / (float) $this->qty_terima, 2) : 0.0;
    }
}
