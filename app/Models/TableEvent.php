<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TableEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'meja_id',
        'bill_id',
        'event_type',
        'event_data',
        'user_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'event_data' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function meja()
    {
        return $this->belongsTo(Meja::class);
    }

    public function bill()
    {
        return $this->belongsTo(Bill::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function log(int $mejaId, string $eventType, ?array $data = null, ?int $userId = null, ?int $billId = null): self
    {
        return self::create([
            'meja_id'    => $mejaId,
            'bill_id'    => $billId,
            'event_type' => $eventType,
            'event_data' => $data,
            'user_id'    => $userId,
            'created_at' => now(),
        ]);
    }
}
