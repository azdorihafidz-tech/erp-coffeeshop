<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\Cabang;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Meja;
use App\Models\OrderQueue;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Publik/guest, TANPA auth (lihat FASE_E_QR_TABLE_ORDERING.md section E2).
 * qr_token dari QR fisik di meja berfungsi sebagai "bukti scan asli",
 * bukan autentikasi user sungguhan.
 */
class PublicOrderController extends Controller
{
    /** Kategori produk_jual yang ditampilkan di menu customer, urut sesuai tab. */
    private const KATEGORI_MENU = ['KPI', 'MBW', 'NKP', 'SNK', 'MKN', 'RTB'];

    public function menu(string $cabang_kode, string $meja_nomor, Request $request)
    {
        $cabang = Cabang::where('kode_cabang', strtoupper($cabang_kode))->first();
        if (! $cabang) {
            abort(404, 'Outlet tidak ditemukan.');
        }

        $meja = Meja::where('cabang_id', $cabang->id)
            ->where('nomor_meja', (int) $meja_nomor)
            ->first();
        if (! $meja) {
            abort(404, 'Meja tidak ditemukan.');
        }

        if ($meja->qr_token !== $request->query('token')) {
            abort(403, 'QR tidak valid.');
        }

        if ($meja->status !== 'aktif') {
            abort(403, 'Meja ini sedang tidak aktif.');
        }

        if (! $cabang->qr_ordering_active) {
            abort(403, 'QR ordering belum aktif untuk outlet ini. Silakan pesan lewat kasir.');
        }

        if ($meja->qr_type === 'temporary' && $meja->qr_expires_at && $meja->qr_expires_at->isPast()) {
            return response()->view('public.order.expired', [], 200);
        }

        $categories = ItemCategory::whereIn('kode_kategori', self::KATEGORI_MENU)
            ->orderByRaw('FIELD(kode_kategori, "' . implode('","', self::KATEGORI_MENU) . '")')
            ->get();

        $items = Item::with('category')
            ->aktif()
            ->produkJual()
            ->whereIn('item_category_id', $categories->pluck('id'))
            ->get()
            ->filter(fn (Item $item) => $item->tersediaDiCabang($cabang->id))
            ->map(function (Item $item) use ($cabang) {
                $item->harga_efektif = $item->hargaEfektifDiCabang($cabang->id);
                $item->tersedia      = $item->bisaDijualDiCabang($cabang->id);
                return $item;
            })
            ->groupBy('item_category_id');

        return view('public.order.menu', [
            'cabang'     => $cabang,
            'meja'       => $meja,
            'categories' => $categories,
            'itemsByKategori' => $items,
            'token'      => $meja->qr_token,
        ]);
    }

    public function submit(Request $request)
    {
        $data = $request->validate([
            'meja_id'                => ['required', 'exists:mejas,id'],
            'token'                  => ['required', 'string'],
            'cart'                   => ['required', 'array', 'min:1'],
            'cart.*.item_id'         => ['required', 'exists:items,id'],
            'cart.*.qty'             => ['required', 'integer', 'min:1'],
            'cart.*.varian'          => ['nullable', 'array'],
            'cart.*.catatan'         => ['nullable', 'string', 'max:255'],
            'customer_name'          => ['nullable', 'string', 'max:100'],
            'customer_phone'         => ['nullable', 'string', 'max:20'],
            'catatan_umum'           => ['nullable', 'string', 'max:500'],
            'payment_mode'           => ['required', Rule::in(['bayar_dulu', 'open_bill'])],
        ]);

        $meja = Meja::with('cabang')->findOrFail($data['meja_id']);

        if ($meja->qr_token !== $data['token']) {
            return response()->json(['message' => 'QR tidak valid.'], 403);
        }

        if (! $meja->cabang->qr_ordering_active) {
            return response()->json(['message' => 'QR ordering belum aktif untuk outlet ini.'], 403);
        }

        // Cek stok cukup (sanity check ringan, bukan reservasi -- stok
        // beneran dipotong nanti saat bill di-close, E4). Reuse helper
        // Item::bisaDijualDiCabang() yang sudah dipakai POS.
        $tidakTersedia = [];
        foreach ($data['cart'] as $row) {
            $item = Item::find($row['item_id']);
            if (! $item || ! $item->bisaDijualDiCabang($meja->cabang_id)) {
                $tidakTersedia[] = $item?->nama_item ?? "item #{$row['item_id']}";
            }
        }

        if (! empty($tidakTersedia)) {
            return response()->json([
                'message' => 'Beberapa item stoknya habis: ' . implode(', ', $tidakTersedia),
            ], 422);
        }

        $queue = OrderQueue::create([
            'meja_id'        => $meja->id,
            'cabang_id'      => $meja->cabang_id,
            'payload'        => $data['cart'],
            'payment_mode'   => $data['payment_mode'],
            'customer_name'  => $data['customer_name'] ?? null,
            'customer_phone' => $data['customer_phone'] ?? null,
            'catatan_umum'   => $data['catatan_umum'] ?? null,
            'status'         => 'pending',
        ]);

        return response()->json(['queue_id' => $queue->id]);
    }

    public function status(int $queue_id)
    {
        $queue = OrderQueue::with('meja.cabang')->findOrFail($queue_id);

        // E6 — estimasi waktu tunggu: item disiapkan paralel oleh
        // barista/kitchen, jadi dipakai MAX (bukan jumlah) waktu_siap_menit
        // dari seluruh item di pesanan ini. Item tanpa estimasi diabaikan.
        $itemIds = collect($queue->payload)->pluck('item_id')->unique();
        $estimasiMenit = Item::whereIn('id', $itemIds)->max('waktu_siap_menit');

        return view('public.order.status', ['queue' => $queue, 'estimasiMenit' => $estimasiMenit]);
    }

    public function pollStatus(int $queue_id)
    {
        $queue = OrderQueue::findOrFail($queue_id);

        $response = ['status' => $queue->status];

        if ($queue->status === 'approved') {
            $bill = $queue->bill_id ? Bill::find($queue->bill_id) : null;
            $response['bill_id']  = $queue->bill_id;
            $response['message']  = $bill
                ? "Pesanan diterima, nomor bill {$bill->nomor_bill}."
                : 'Pesanan diterima kasir.';
        } elseif ($queue->status === 'rejected') {
            $response['message'] = $queue->rejected_reason ?: 'Pesanan ditolak kasir.';
        }

        return response()->json($response);
    }
}
