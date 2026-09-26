<?php

namespace App\Models;

use App\Traits\FillsDeletedBy;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Roastery V2 Minggu 1-2 (2026-09-26) — Master Petani (pemasok buah kopi
 * cherry). Pola sama seperti Supplier (global, tidak diikat ke cabang
 * tertentu) — lihat FASE_ROASTERY_V2_DESIGN.md section 3.
 */
class Petani extends Model
{
    use HasFactory, HasAuditLog, SoftDeletes, FillsDeletedBy;

    protected $table = 'petani';

    protected $fillable = [
        'kode_petani',
        'nama',
        'telepon',
        'alamat',
        'nama_kebun',
        'koordinat_lat',
        'koordinat_lng',
        'catatan',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active'     => 'boolean',
            'koordinat_lat' => 'decimal:7',
            'koordinat_lng' => 'decimal:7',
        ];
    }

    public function scopeAktif($query)
    {
        return $query->where('is_active', true);
    }
}
