<?php

namespace App\Models;

use App\Enums\PackSize;
use Illuminate\Database\Eloquent\Model;

class PackingBatch extends Model
{
    protected $fillable = [
        'kode_batch', 'tanggal', 'cabang_id', 'user_id',
        'source_type', 'source_item_id', 'source_qty_kg_in',
        'target_item_id', 'target_pack_size', 'target_qty_pack',
        'waste_kg', 'cost_per_pack', 'status', 'catatan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal'           => 'date',
            'source_qty_kg_in'  => 'decimal:3',
            'target_pack_size'  => PackSize::class,
            'waste_kg'          => 'decimal:3',
            'cost_per_pack'     => 'decimal:2',
        ];
    }

    public function cabang()     { return $this->belongsTo(Cabang::class); }
    public function user()       { return $this->belongsTo(User::class); }
    public function sourceItem() { return $this->belongsTo(Item::class, 'source_item_id'); }
    public function targetItem() { return $this->belongsTo(Item::class, 'target_item_id'); }
}
