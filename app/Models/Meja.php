<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Meja extends Model
{
    protected $table = 'mejas';

    protected $fillable = [
        'cabang_id',
        'nomor_meja',
        'nama_meja',
        'kapasitas',
        'lokasi',
        'qr_token',
        'qr_type',
        'qr_expires_at',
        'status',
        'catatan',
    ];

    protected function casts(): array
    {
        return [
            'qr_expires_at' => 'datetime',
            'kapasitas'     => 'integer',
        ];
    }

    public function cabang()
    {
        return $this->belongsTo(Cabang::class);
    }

    public function bills()
    {
        return $this->hasMany(Bill::class);
    }

    public function orderQueues()
    {
        return $this->hasMany(OrderQueue::class);
    }

    public function scopeAktif($query)
    {
        return $query->where('status', 'aktif');
    }

    public function scopePermanent($query)
    {
        return $query->where('qr_type', 'permanent');
    }

    public static function generateQrToken(): string
    {
        do {
            $token = Str::random(32);
        } while (self::where('qr_token', $token)->exists());

        return $token;
    }
}
