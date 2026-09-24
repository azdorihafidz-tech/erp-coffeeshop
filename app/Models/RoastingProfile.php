<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoastingProfile extends Model
{
    protected $fillable = [
        'nama', 'slug', 'level_target', 'avg_susut_percent',
        'suhu_target_celsius', 'waktu_target_menit', 'catatan', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'avg_susut_percent' => 'decimal:2',
            'is_active'         => 'boolean',
        ];
    }

    public function batches()
    {
        return $this->hasMany(RoastingBatch::class, 'profile_id');
    }

    public function scopeAktif($query)
    {
        return $query->where('is_active', true);
    }

    /** Default susut per level (Q3 Owner): light 12, medium 15, dark 18. */
    public static function seedDefault(): void
    {
        foreach ([['Light', 'light', 12], ['Medium', 'medium', 15], ['Dark', 'dark', 18]] as [$nama, $level, $susut]) {
            self::updateOrCreate(['slug' => $level], [
                'nama'              => $nama,
                'level_target'      => $level,
                'avg_susut_percent' => $susut,
                'is_active'         => true,
            ]);
        }
    }
}
