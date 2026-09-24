<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillItem extends Model
{
    protected $fillable = [
        'bill_id',
        'item_id',
        'qty',
        'harga_satuan',
        'subtotal',
        'varian',
        'catatan',
        'status_dapur',
        'status_dapur_at',
        'status_dapur_by',
        'komplain_reason',
        'urutan_masuk',
    ];

    protected function casts(): array
    {
        return [
            'varian'          => 'array',
            'qty'             => 'decimal:3',
            'harga_satuan'    => 'decimal:2',
            'subtotal'        => 'decimal:2',
            'status_dapur_at' => 'datetime',
        ];
    }

    public function bill()
    {
        return $this->belongsTo(Bill::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function statusDapurBy()
    {
        return $this->belongsTo(User::class, 'status_dapur_by');
    }

    public function scopePending($query)
    {
        return $query->where('status_dapur', 'pending');
    }

    public function scopeSiap($query)
    {
        return $query->where('status_dapur', 'siap');
    }

    public function scopeKomplain($query)
    {
        return $query->where('status_dapur', 'komplain');
    }

    public function markSiap(int $userId): void
    {
        $this->update([
            'status_dapur'    => 'siap',
            'status_dapur_at' => now(),
            'status_dapur_by' => $userId,
        ]);
    }

    public function markKomplain(int $userId, string $reason): void
    {
        $this->update([
            'status_dapur'     => 'komplain',
            'status_dapur_at'  => now(),
            'status_dapur_by'  => $userId,
            'komplain_reason'  => $reason,
        ]);
    }
}
