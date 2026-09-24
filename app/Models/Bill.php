<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bill extends Model
{
    protected $fillable = [
        'meja_id',
        'cabang_id',
        'nomor_bill',
        'status',
        'sub_total',
        'diskon',
        'pajak',
        'total',
        'catatan',
        'sumber_awal',
        'started_at',
        'closed_at',
        'created_by',
        'closed_by',
        'transferred_from_meja_id',
        'transferred_at',
        'customer_name',
        'customer_phone',
        'payment_mode',
    ];

    protected function casts(): array
    {
        return [
            'started_at'      => 'datetime',
            'closed_at'       => 'datetime',
            'transferred_at'  => 'datetime',
            'sub_total'       => 'decimal:2',
            'diskon'          => 'decimal:2',
            'pajak'           => 'decimal:2',
            'total'           => 'decimal:2',
        ];
    }

    public function meja()
    {
        return $this->belongsTo(Meja::class);
    }

    public function cabang()
    {
        return $this->belongsTo(Cabang::class);
    }

    public function items()
    {
        return $this->hasMany(BillItem::class);
    }

    public function orderQueues()
    {
        return $this->hasMany(OrderQueue::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function closedBy()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function transferredFromMeja()
    {
        return $this->belongsTo(Meja::class, 'transferred_from_meja_id');
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeWaitingPayment($query)
    {
        return $query->where('status', 'waiting_payment');
    }

    public function scopeClosed($query)
    {
        return $query->where('status', 'closed');
    }

    /** Bikin bill baru buat 1 meja (dipanggil dari BillService, bukan langsung dari controller). */
    public static function open(Meja $meja, array $data = []): self
    {
        return self::create(array_merge([
            'meja_id'     => $meja->id,
            'cabang_id'   => $meja->cabang_id,
            'nomor_bill'  => self::generateNomorBill($meja->cabang_id),
            'status'      => 'open',
            'sumber_awal' => 'kasir',
            'started_at'  => now(),
            'created_by'  => auth()->id(),
        ], $data));
    }

    public static function generateNomorBill(int $cabangId): string
    {
        $cabang = Cabang::find($cabangId);
        $kode = $cabang?->kode_cabang ?? 'BL';
        $tanggal = now()->format('ymd');
        $urut = self::where('cabang_id', $cabangId)
            ->whereDate('created_at', today())
            ->count() + 1;

        return sprintf('BILL-%s-%s-%03d', $kode, $tanggal, $urut);
    }

    /**
     * Tambah 1 baris item ke bill ini. $hargaOverride dipakai kalau harga
     * sudah dihitung di caller (mis. POS legacy dgn ItemVariant override,
     * `bill_items` belum punya kolom `item_variant_id` sendiri — E4.3,
     * simpan info varian sbg metadata di kolom `varian` JSON kalau perlu).
     */
    public function addItem(int $itemId, float $qty, ?array $varian = null, ?string $catatan = null, ?float $hargaOverride = null): BillItem
    {
        $item = Item::findOrFail($itemId);
        $harga = $hargaOverride ?? $item->hargaEfektifDiCabang($this->cabang_id);

        $billItem = $this->items()->create([
            'item_id'       => $itemId,
            'qty'           => $qty,
            'harga_satuan'  => $harga,
            'subtotal'      => $harga * $qty,
            'varian'        => $varian,
            'catatan'       => $catatan,
            'status_dapur'  => 'pending',
            'urutan_masuk'  => $this->items()->max('urutan_masuk') + 1,
        ]);

        $this->recalculateTotal();

        return $billItem;
    }

    /** Hitung ulang sub_total/total dari bill_items, simpan ke kolom. */
    public function recalculateTotal(): void
    {
        $subTotal = $this->items()->sum('subtotal');

        $this->update([
            'sub_total' => $subTotal,
            'total'     => max(0, $subTotal - $this->diskon + $this->pajak),
        ]);
    }

    /** Pindah bill ini ke meja lain, catat audit trail di kedua meja. */
    public function transferTo(Meja $mejaBaru, ?int $userId = null): void
    {
        $mejaLama = $this->meja;

        $this->update([
            'meja_id'                  => $mejaBaru->id,
            'transferred_from_meja_id' => $mejaLama->id,
            'transferred_at'           => now(),
        ]);

        TableEvent::log($mejaLama->id, 'transferred', [
            'ke_meja_id' => $mejaBaru->id,
            'bill_id'    => $this->id,
        ], $userId, $this->id);

        TableEvent::log($mejaBaru->id, 'transferred', [
            'dari_meja_id' => $mejaLama->id,
            'bill_id'      => $this->id,
        ], $userId, $this->id);
    }
}
