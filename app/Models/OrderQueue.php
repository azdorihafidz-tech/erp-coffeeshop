<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderQueue extends Model
{
    protected $fillable = [
        'meja_id',
        'cabang_id',
        'payload',
        'payment_mode',
        'customer_name',
        'customer_phone',
        'catatan_umum',
        'status',
        'approved_at',
        'approved_by',
        'rejected_reason',
        'bill_id',
    ];

    protected function casts(): array
    {
        return [
            'payload'     => 'array',
            'approved_at' => 'datetime',
        ];
    }

    public function meja()
    {
        return $this->belongsTo(Meja::class);
    }

    public function cabang()
    {
        return $this->belongsTo(Cabang::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function bill()
    {
        return $this->belongsTo(Bill::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeForCabang($query, int $cabangId)
    {
        return $query->where('cabang_id', $cabangId);
    }

    public function scopeForMeja($query, int $mejaId)
    {
        return $query->where('meja_id', $mejaId);
    }
}
