<?php

namespace App\Http\Controllers;

use App\Enums\StatusOrder;
use App\Models\Cabang;
use App\Models\Kas;
use App\Models\Order;
use App\Services\PenjualanService;
use App\Support\CabangLaporan;
use Illuminate\Http\Request;

/**
 * Kiriman Cabang (FASE_PRODUKSI_GEROBAK_DESIGN.md Tahap 2): daftar kiriman
 * gudang/roastery → cabang. Cabang tujuan menekan Terima (boleh jumlah
 * kurang) atau Tolak; pengirim boleh membatalkan selama belum diterima.
 * Filter cabang manual (CLAUDE.md 3.4) lewat CabangLaporan.
 */
class KirimanCabangController extends Controller
{
    public function __construct(private PenjualanService $penjualanService) {}

    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('kiriman.view'), 403);

        $arah = $request->input('arah') === 'keluar' ? 'keluar' : 'masuk';
        $status = $request->input('status', $arah === 'masuk' ? 'menunggu' : '');
        $cabangId = CabangLaporan::id();
        $kolom = $arah === 'masuk' ? 'outlet_tujuan_id' : 'cabang_id';

        $kiriman = Order::with(['cabang', 'outletTujuan', 'items', 'diterimaOleh'])
            ->where('customer_type', 'internal')
            ->whereNotNull('status_kirim')
            ->when($cabangId, fn ($q) => $q->where($kolom, $cabangId))
            ->when(in_array($status, ['menunggu', 'diterima', 'ditolak', 'dibatalkan'], true), fn ($q) => $q->where('status_kirim', $status))
            ->orderByRaw("status_kirim = 'menunggu' DESC")
            ->orderByDesc('created_at')
            ->paginate(20)->withQueryString();

        return view('kiriman.index', [
            'kiriman'  => $kiriman,
            'arah'     => $arah,
            'status'   => $status,
            'cabang'   => $cabangId ? Cabang::find($cabangId) : null,
            'menunggu' => self::jumlahMenunggu(),
        ]);
    }

    public function show(Order $order)
    {
        abort_unless(auth()->user()->can('kiriman.view'), 403);
        $this->pastikanBolehLihat($order);

        $order->load(['cabang', 'outletTujuan', 'items', 'kirimanItems.item', 'diterimaOleh', 'kasir']);
        $kasTujuan = Kas::where('cabang_id', $order->outlet_tujuan_id)->where('default_untuk', 'tunai')->where('is_active', true)->first();

        return view('kiriman.show', [
            'order'       => $order,
            'kasTujuan'   => $kasTujuan,
            'bisaTerima'  => $order->status_kirim === 'menunggu' && auth()->user()->can('kiriman.terima') && CabangLaporan::bolehAkses($order->outlet_tujuan_id),
            'bisaBatal'   => $order->status_kirim === 'menunggu' && auth()->user()->can('order.batalkan') && CabangLaporan::bolehAkses($order->cabang_id),
        ]);
    }

    public function terima(Request $request, Order $order)
    {
        abort_unless(auth()->user()->can('kiriman.terima'), 403);
        abort_unless($order->isKirimanCabang() && CabangLaporan::bolehAkses($order->outlet_tujuan_id), 403, 'Hanya cabang tujuan yang bisa menerima kiriman ini.');

        $data = $request->validate([
            'qty_diterima'   => ['nullable', 'array'],
            'qty_diterima.*' => ['nullable', 'numeric', 'min:0'],
        ], ['qty_diterima.*.min' => 'Jumlah diterima tidak boleh minus.']);

        try {
            $order = $this->penjualanService->terimaKiriman($order, $data['qty_diterima'] ?? [], auth()->id());
        } catch (\Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        activity('Order')->performedOn($order)->causedBy(auth()->user())->log("Kiriman {$order->nomor_order} diterima oleh " . auth()->user()->name);

        return redirect()->route('kiriman.show', $order)
            ->with('success', "Kiriman {$order->nomor_order} diterima — stok masuk dan kas tunai terpotong Rp " . number_format((float) $order->total_bayar, 0, ',', '.') . '.');
    }

    public function tolak(Request $request, Order $order)
    {
        abort_unless(auth()->user()->can('kiriman.terima'), 403);
        abort_unless($order->isKirimanCabang() && CabangLaporan::bolehAkses($order->outlet_tujuan_id), 403, 'Hanya cabang tujuan yang bisa menolak kiriman ini.');

        $data = $request->validate(['alasan' => ['required', 'string', 'min:3', 'max:500']], ['alasan.required' => 'Alasan menolak wajib diisi.']);

        try {
            $this->penjualanService->tolakKiriman($order, $data['alasan'], auth()->id());
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        activity('Order')->performedOn($order)->causedBy(auth()->user())->log("Kiriman {$order->nomor_order} ditolak oleh " . auth()->user()->name . ": {$data['alasan']}");

        return redirect()->route('kiriman.show', $order)->with('success', "Kiriman {$order->nomor_order} ditolak — barang kembali ke stok pengirim.");
    }

    public function batalkan(Request $request, Order $order)
    {
        abort_unless(auth()->user()->can('order.batalkan'), 403);
        abort_unless($order->isKirimanCabang() && CabangLaporan::bolehAkses($order->cabang_id), 403, 'Hanya cabang pengirim yang bisa membatalkan kiriman ini.');

        $data = $request->validate(['alasan' => ['required', 'string', 'min:3', 'max:500']], ['alasan.required' => 'Alasan pembatalan wajib diisi.']);

        if ($order->status !== StatusOrder::Proses) {
            return back()->with('error', 'Hanya kiriman yang belum diterima yang bisa dibatalkan.');
        }

        try {
            $this->penjualanService->batalkan($order);
            $order->update(['alasan_pembatalan_kategori' => 'Kiriman dibatalkan', 'alasan_pembatalan_detail' => $data['alasan'], 'alasan_tolak' => $data['alasan']]);
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        activity('Order')->performedOn($order)->causedBy(auth()->user())->log("Kiriman {$order->nomor_order} dibatalkan pengirim: {$data['alasan']}");

        return redirect()->route('kiriman.show', $order)->with('success', "Kiriman {$order->nomor_order} dibatalkan — barang kembali ke stok.");
    }

    /** Jumlah kiriman menunggu diterima untuk cabang aktif (badge sidebar). */
    public static function jumlahMenunggu(): int
    {
        $cabangId = CabangLaporan::id();

        return Order::where('customer_type', 'internal')->where('status_kirim', 'menunggu')
            ->when($cabangId, fn ($q) => $q->where('outlet_tujuan_id', $cabangId))
            ->count();
    }

    private function pastikanBolehLihat(Order $order): void
    {
        abort_unless($order->isKirimanCabang(), 404);
        abort_unless(CabangLaporan::bolehAkses($order->outlet_tujuan_id) || CabangLaporan::bolehAkses($order->cabang_id), 403, 'Kiriman ini bukan untuk/dari cabang Anda.');
    }
}
