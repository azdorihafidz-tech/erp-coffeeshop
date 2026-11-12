<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResepBumbu extends Model
{
    protected $table = 'resep_bumbu';

    protected $fillable = [
        'nama',
        'kode',
        'jenis_olahan_id',
        // Tahap 3 D'mentai — resep otomatis ketemu saat kasir klik produk di POS
        'item_id',
        'is_active',
        'catatan',
        'dibuat_oleh',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** Panjang kolom resep_bumbu.kode (migration 2026_07_23_000002). */
    public const KODE_MAKS = 20;

    /**
     * Kode resep komposisi untuk Produk Jual (2026-11-12, bug WONGKO PI).
     * Bawaan "RESEP-{kode_item}", tapi kode_item boleh sampai 30 huruf sedangkan
     * kolom kode hanya 20 → simpan komposisi pertama kali gagal (SQL 1406).
     * Kalau terlalu panjang atau sudah dipakai resep lain: "RESEP-ID{id item}".
     */
    public static function kodeUntukItem(Item $item): string
    {
        $kode = 'RESEP-' . $item->kode_item;
        if (mb_strlen($kode) <= self::KODE_MAKS && ! static::where('kode', $kode)->exists()) {
            return $kode;
        }
        $dasar = 'RESEP-ID' . $item->id;
        $kode = $dasar;
        for ($i = 2; static::where('kode', $kode)->exists(); $i++) {
            $kode = $dasar . '-' . $i;
        }

        return $kode;
    }

    public function items()
    {
        return $this->hasMany(ResepBumbuItem::class)->orderBy('urutan');
    }

    public function jenisOlahan()
    {
        return $this->belongsTo(JenisOlahan::class, 'jenis_olahan_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function dibuatOleh()
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    public function scopeAktif($query)
    {
        return $query->where('is_active', true);
    }
}
