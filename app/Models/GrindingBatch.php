<?php

namespace App\Models;

use App\Enums\GrindSize;
use Illuminate\Database\Eloquent\Model;

class GrindingBatch extends Model
{
    protected $fillable = [
        'kode_batch', 'tanggal', 'cabang_id', 'user_id',
        'roasted_item_id', 'roasted_qty_kg_in', 'grind_size',
        'ground_item_id', 'ground_qty_kg_out', 'waste_kg', 'cost_per_kg',
        'catatan', 'status',
    ];

    protected function casts(): array
    {
        return [
            'tanggal'            => 'date',
            'roasted_qty_kg_in'  => 'decimal:3',
            'grind_size'         => GrindSize::class,
            'ground_qty_kg_out'  => 'decimal:3',
            'waste_kg'           => 'decimal:3',
            'cost_per_kg'        => 'decimal:2',
        ];
    }

    public function cabang()      { return $this->belongsTo(Cabang::class); }
    public function user()        { return $this->belongsTo(User::class); }
    public function roastedItem() { return $this->belongsTo(Item::class, 'roasted_item_id'); }
    public function groundItem()  { return $this->belongsTo(Item::class, 'ground_item_id'); }
}
