<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoastingBatch extends Model
{
    protected $fillable = [
        'nomor_batch', 'tanggal', 'cabang_id', 'user_id', 'profile_id',
        'green_bean_item_id', 'green_qty_kg', 'roasted_curah_item_id', 'roasted_qty_kg',
        'waste_qty_kg', 'yield_rate_percent', 'cost_awal', 'cost_per_kg_roasted',
        'catatan', 'status', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'tanggal'             => 'date',
            'green_qty_kg'        => 'decimal:3',
            'roasted_qty_kg'      => 'decimal:3',
            'waste_qty_kg'        => 'decimal:3',
            'yield_rate_percent'  => 'decimal:2',
            'cost_awal'           => 'decimal:2',
            'cost_per_kg_roasted' => 'decimal:2',
            'completed_at'        => 'datetime',
        ];
    }

    public function cabang()      { return $this->belongsTo(Cabang::class); }
    public function user()        { return $this->belongsTo(User::class); }
    public function profile()     { return $this->belongsTo(RoastingProfile::class, 'profile_id'); }
    public function greenBean()   { return $this->belongsTo(Item::class, 'green_bean_item_id'); }
    public function roastedCurah(){ return $this->belongsTo(Item::class, 'roasted_curah_item_id'); }
    public function packs()       { return $this->hasMany(RoastingBatchPack::class, 'batch_id'); }

    /** Kg roasted curah yang belum dikemas (hanya berarti untuk batch completed). */
    public function getSisaCurahKgAttribute(): float
    {
        return round((float) $this->roasted_qty_kg - (float) $this->packs->sum('total_berat_kg'), 3);
    }
}
