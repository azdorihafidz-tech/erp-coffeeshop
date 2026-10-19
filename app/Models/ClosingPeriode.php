<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Sprint Closing Periode (2026-10-17) — 1 baris = 1 closing (draft/final/
 * dibuka). `cabang_id` NULL = Konsolidasi semua cabang. Snapshot angka ada
 * di kolom `data` (JSON) dan TIDAK pernah dihitung ulang setelah final.
 */
class ClosingPeriode extends Model
{
    public const STATUS_DRAFT  = 'draft';
    public const STATUS_FINAL  = 'final';
    public const STATUS_DIBUKA = 'dibuka';

    public const JENIS = [
        'harian'   => 'Harian',
        'mingguan' => 'Mingguan',
        'bulanan'  => 'Bulanan',
        'custom'   => 'Custom',
    ];

    protected $fillable = [
        'kode', 'jenis', 'tanggal_mulai', 'tanggal_selesai', 'cabang_id',
        'status', 'versi', 'parent_id',
        'total_omzet', 'total_hpp', 'laba_bersih', 'total_kas_akhir', 'data',
        'catatan', 'dibuat_oleh', 'dihitung_pada',
        'difinalkan_oleh', 'difinalkan_pada',
        'dibuka_oleh', 'dibuka_pada', 'alasan_buka',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_mulai'   => 'date',
            'tanggal_selesai' => 'date',
            'data'            => 'array',
            'total_omzet'     => 'decimal:2',
            'total_hpp'       => 'decimal:2',
            'laba_bersih'     => 'decimal:2',
            'total_kas_akhir' => 'decimal:2',
            'dihitung_pada'   => 'datetime',
            'difinalkan_pada' => 'datetime',
            'dibuka_pada'     => 'datetime',
        ];
    }

    public function cabang()
    {
        return $this->belongsTo(Cabang::class);
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function dibuatOleh()
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    public function difinalkanOleh()
    {
        return $this->belongsTo(User::class, 'difinalkan_oleh');
    }

    public function dibukaOleh()
    {
        return $this->belongsTo(User::class, 'dibuka_oleh');
    }

    public function isKonsolidasi(): bool
    {
        return $this->cabang_id === null;
    }

    public function namaCakupan(): string
    {
        return $this->isKonsolidasi() ? 'Konsolidasi (Semua Cabang)' : ($this->cabang?->nama_cabang ?? '-');
    }

    public function labelJenis(): string
    {
        return self::JENIS[$this->jenis] ?? ucfirst($this->jenis);
    }

    public function labelPeriode(): string
    {
        $mulai = $this->tanggal_mulai->translatedFormat('d M Y');
        $selesai = $this->tanggal_selesai->translatedFormat('d M Y');

        return $mulai === $selesai ? $mulai : "{$mulai} s/d {$selesai}";
    }

    public function badgeStatus(): string
    {
        return match ($this->status) {
            self::STATUS_FINAL  => '<span class="badge bg-success"><i class="bi bi-lock-fill me-1"></i>Final</span>',
            self::STATUS_DIBUKA => '<span class="badge bg-secondary"><i class="bi bi-unlock me-1"></i>Dibuka</span>',
            default             => '<span class="badge bg-warning text-dark"><i class="bi bi-pencil me-1"></i>Draft</span>',
        };
    }
}
