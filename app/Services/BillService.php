<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Meja;
use App\Models\OrderQueue;
use App\Models\TableEvent;
use Illuminate\Support\Facades\DB;

/**
 * Fase E3 — kasir side: approve/reject queue, tandai meja terisi, tambah
 * item manual, transfer meja, close bill jadi penjualan real. Lihat
 * FASE_E_QR_TABLE_ORDERING.md section E3/E4.
 */
class BillService
{
    public function __construct(private PenjualanService $penjualanService) {}

    /** Approve order dari QR -> bikin/reuse bill open utk meja, insert bill_items dari payload. */
    public function approveQueue(int $queueId, int $userId): Bill
    {
        return DB::transaction(function () use ($queueId, $userId) {
            $queue = OrderQueue::lockForUpdate()->findOrFail($queueId);

            if ($queue->status !== 'pending') {
                throw new \Exception('Order ini sudah diproses sebelumnya (bukan lagi pending).');
            }

            $meja = Meja::findOrFail($queue->meja_id);

            $bill = Bill::where('meja_id', $meja->id)
                ->whereIn('status', ['open', 'waiting_payment'])
                ->first();

            if (! $bill) {
                // order_queues.payment_mode pakai vocab 'open_bill' (E2),
                // bills.payment_mode pakai vocab 'bayar_di_kasir' (E3) --
                // sama makna, beda kata, translate di sini.
                $bill = Bill::open($meja, [
                    'sumber_awal'    => 'qr',
                    'payment_mode'   => $queue->payment_mode === 'bayar_dulu' ? 'bayar_dulu' : 'bayar_di_kasir',
                    'customer_name'  => $queue->customer_name,
                    'customer_phone' => $queue->customer_phone,
                ]);
                TableEvent::log($meja->id, 'occupied', ['bill_id' => $bill->id, 'sumber' => 'qr'], $userId, $bill->id);
            }

            foreach ($queue->payload as $row) {
                $bill->addItem(
                    (int) $row['item_id'],
                    (float) $row['qty'],
                    $row['varian'] ?? null,
                    $row['catatan'] ?? null
                );
            }

            $queue->update([
                'status'      => 'approved',
                'approved_at' => now(),
                'approved_by' => $userId,
                'bill_id'     => $bill->id,
            ]);

            TableEvent::log($meja->id, 'order_added', ['queue_id' => $queue->id, 'item_count' => count($queue->payload)], $userId, $bill->id);
            TableEvent::log($meja->id, 'approved', ['queue_id' => $queue->id], $userId, $bill->id);

            return $bill->fresh('items');
        });
    }

    public function rejectQueue(int $queueId, int $userId, string $reason): void
    {
        $queue = OrderQueue::findOrFail($queueId);

        if ($queue->status !== 'pending') {
            throw new \Exception('Order ini sudah diproses sebelumnya (bukan lagi pending).');
        }

        $queue->update([
            'status'           => 'rejected',
            'rejected_reason'  => $reason,
            'approved_by'      => $userId,
        ]);
    }

    /** Kasir tandai meja terisi manual (walk-in, belum tentu ada order). */
    public function tandaiMejaTerisi(int $mejaId, int $userId): Bill
    {
        $meja = Meja::findOrFail($mejaId);

        $existing = Bill::where('meja_id', $meja->id)->whereIn('status', ['open', 'waiting_payment'])->first();
        if ($existing) {
            return $existing;
        }

        $bill = Bill::open($meja, ['sumber_awal' => 'kasir']);
        TableEvent::log($meja->id, 'occupied', ['bill_id' => $bill->id, 'sumber' => 'kasir'], $userId, $bill->id);

        return $bill;
    }

    public function addItemManual(int $billId, int $itemId, float $qty, ?array $varian, ?string $catatan, int $userId, ?float $hargaOverride = null): Bill
    {
        $bill = Bill::findOrFail($billId);

        $bill->addItem($itemId, $qty, $varian, $catatan, $hargaOverride);

        TableEvent::log($bill->meja_id, 'order_added', ['item_id' => $itemId, 'qty' => $qty, 'sumber' => 'kasir'], $userId, $bill->id);

        return $bill->fresh('items');
    }

    /**
     * E4.3 — batalkan bill yang belum dibayar. Bill open/waiting_payment
     * belum pernah potong stok/catat kas sama sekali (sama seperti pola
     * lama `PenjualanService::batalkan()` utk Order Pending), jadi cukup
     * ubah status + audit trail, TANPA reversal apapun.
     */
    public function cancelBill(int $billId, int $userId, ?string $reason = null): Bill
    {
        $bill = Bill::findOrFail($billId);

        if (! in_array($bill->status, ['open', 'waiting_payment'], true)) {
            throw new \Exception('Bill ini sudah bukan berstatus terbuka (mungkin sudah dibayar/dibatalkan) — tidak bisa dibatalkan lewat aksi ini.');
        }

        $bill->update(['status' => 'cancelled']);

        TableEvent::log($bill->meja_id, 'vacated', ['bill_id' => $bill->id, 'alasan' => 'cancelled', 'reason' => $reason], $userId, $bill->id);

        return $bill->fresh();
    }

    public function transferMeja(int $billId, int $mejaBaruId, int $userId): Bill
    {
        $bill = Bill::findOrFail($billId);
        $mejaBaru = Meja::findOrFail($mejaBaruId);

        $bentrok = Bill::where('meja_id', $mejaBaru->id)->whereIn('status', ['open', 'waiting_payment'])->exists();
        if ($bentrok) {
            throw new \Exception("Meja {$mejaBaru->nama_meja} sudah ada bill aktif lain.");
        }

        $bill->transferTo($mejaBaru, $userId);

        return $bill->fresh();
    }

    /**
     * Tutup bill jadi transaksi penjualan real — reuse PenjualanService
     * (potong stok via resep bumbu, catat kas) SUPAYA tidak duplikat logic
     * yang sudah battle-tested (CLAUDE.md 3.3).
     */
    public function closeBill(int $billId, array $paymentData, int $userId): Bill
    {
        return DB::transaction(function () use ($billId, $paymentData, $userId) {
            $bill = Bill::with('items.item', 'meja')->lockForUpdate()->findOrFail($billId);

            if ($bill->status === 'closed') {
                throw new \Exception('Bill ini sudah ditutup sebelumnya.');
            }

            if ($bill->items->isEmpty()) {
                throw new \Exception('Bill belum ada item, tidak bisa dibayar.');
            }

            $items = $bill->items->map(function ($bi) {
                $item = $bi->item;
                return [
                    'item_id'      => $bi->item_id,
                    'nama_item'    => $item->nama_item,
                    'harga_satuan' => (float) $bi->harga_satuan,
                    'satuan'       => $item->satuan,
                    'qty'          => (float) $bi->qty,
                    'catatan'      => $bi->catatan,
                ];
            })->all();

            $order = $this->penjualanService->buatOrder([
                'tipe_transaksi'  => 'dine_in',
                'nomor_meja'      => (string) $bill->meja->nomor_meja,
                'meja_id'         => $bill->meja_id,
                'items'           => $items,
                'diskon'          => (float) $bill->diskon,
                'nama_pelanggan'  => $bill->customer_name,
                'telepon_pelanggan' => $bill->customer_phone,
                'catatan'         => $bill->catatan,
                'payments'        => $paymentData['payments'],
            ], $bill->cabang_id);

            $bill->update([
                'status'    => 'closed',
                'closed_at' => now(),
                'closed_by' => $userId,
                'total'     => $order->total_bayar,
            ]);

            TableEvent::log($bill->meja_id, 'paid', ['bill_id' => $bill->id, 'order_id' => $order->id], $userId, $bill->id);
            TableEvent::log($bill->meja_id, 'vacated', ['bill_id' => $bill->id], $userId, $bill->id);

            return $bill->fresh();
        });
    }
}
