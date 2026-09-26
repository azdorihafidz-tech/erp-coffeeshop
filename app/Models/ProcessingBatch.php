<?php

namespace App\Models;

use App\Enums\ProcessingMethod;
use Illuminate\Database\Eloquent\Model;

class ProcessingBatch extends Model
{
    protected $fillable = [
        'kode_batch', 'tanggal_mulai', 'tanggal_selesai', 'cabang_id', 'user_id',
        'cherry_item_id', 'cherry_qty_kg', 'cherry_cost_awal', 'processing_method',
        'fermentasi_start', 'fermentasi_end', 'fermentasi_suhu_celsius', 'fermentasi_catatan',
        'drying_start', 'drying_end', 'drying_catatan',
        'hulling_qty_kg',
        'sortir_qty_kg', 'sortir_defect_kg',
        'green_bean_item_id', 'status', 'yield_percent', 'cost_per_kg_green', 'catatan_umum',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_mulai'      => 'date',
            'tanggal_selesai'    => 'date',
            'cherry_qty_kg'      => 'decimal:3',
            'cherry_cost_awal'   => 'decimal:2',
            'processing_method'  => ProcessingMethod::class,
            'fermentasi_start'   => 'datetime',
            'fermentasi_end'     => 'datetime',
            'drying_start'       => 'datetime',
            'drying_end'         => 'datetime',
            'hulling_qty_kg'     => 'decimal:3',
            'sortir_qty_kg'      => 'decimal:3',
            'sortir_defect_kg'   => 'decimal:3',
            'yield_percent'      => 'decimal:2',
            'cost_per_kg_green'  => 'decimal:2',
        ];
    }

    public function cabang()     { return $this->belongsTo(Cabang::class); }
    public function user()       { return $this->belongsTo(User::class); }
    public function cherryItem() { return $this->belongsTo(Item::class, 'cherry_item_id'); }
    public function greenBean()  { return $this->belongsTo(Item::class, 'green_bean_item_id'); }
}
