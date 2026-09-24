<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KitchenDisplaySetting extends Model
{
    protected $fillable = [
        'cabang_id',
        'is_active',
        'auto_refresh_seconds',
        'alert_sound_url',
    ];

    protected function casts(): array
    {
        return [
            'is_active'             => 'boolean',
            'auto_refresh_seconds'  => 'integer',
        ];
    }

    public function cabang()
    {
        return $this->belongsTo(Cabang::class);
    }

    /** Ambil setting cabang, atau default (nonaktif, refresh 10s) kalau belum pernah diset. */
    public static function forCabang(int $cabangId): self
    {
        return self::firstOrNew(['cabang_id' => $cabangId], [
            'is_active'            => false,
            'auto_refresh_seconds' => 10,
        ]);
    }
}
