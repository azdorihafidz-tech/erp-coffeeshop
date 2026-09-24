<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoastingBatchPack extends Model
{
    protected $fillable = [
        'batch_id', 'item_pack_id', 'qty_pack', 'berat_per_pack_gr',
        'total_berat_kg', 'cost_per_pack', 'user_id',
    ];

    protected function casts(): array
    {
        return ['total_berat_kg' => 'decimal:3', 'cost_per_pack' => 'decimal:2'];
    }

    public function batch()    { return $this->belongsTo(RoastingBatch::class, 'batch_id'); }
    public function itemPack() { return $this->belongsTo(Item::class, 'item_pack_id'); }
}
