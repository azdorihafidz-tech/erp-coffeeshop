<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\Cabang;
use App\Models\Meja;
use App\Models\OrderQueue;
use App\Services\BillService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KasirMejaController extends Controller
{
    public function __construct(private BillService $billService) {}

    public function layoutMeja(Request $request, ?int $cabang = null)
    {
        abort_unless(auth()->user()->can('kasir.layout-meja.view'), 403);

        $cabangId = $this->resolveCabangId($cabang);
        $cabangModel = Cabang::findOrFail($cabangId);

        $mejas = Meja::with(['bills' => fn ($q) => $q->whereIn('status', ['open', 'waiting_payment'])->with('items')])
            ->where('cabang_id', $cabangId)
            ->aktif()
            ->orderBy('nomor_meja')
            ->get();

        $queuePendingCount = OrderQueue::forCabang($cabangId)->pending()->count();

        return view('kasir.meja.layout', [
            'cabang'            => $cabangModel,
            'lokasiList'        => $this->cabangPilihan(),
            'mejas'             => $mejas,
            'queuePendingCount' => $queuePendingCount,
        ]);
    }

    /**
     * E6 — polling ringan untuk badge notifikasi Layout Meja (queue pending +
     * item komplain dapur), tanpa perlu reload halaman penuh.
     */
    public function pollNotifikasi(int $cabang)
    {
        abort_unless(auth()->user()->can('kasir.layout-meja.view'), 403);

        $queuePendingCount = OrderQueue::forCabang($cabang)->pending()->count();

        $komplainCount = \App\Models\BillItem::whereHas('bill', fn ($q) => $q->where('cabang_id', $cabang)->whereIn('status', ['open', 'waiting_payment']))
            ->where('status_dapur', 'komplain')
            ->count();

        return response()->json([
            'queue_pending_count' => $queuePendingCount,
            'komplain_count'      => $komplainCount,
        ]);
    }

    public function indexQueue(Request $request)
    {
        abort_unless(auth()->user()->can('kasir.queue.view'), 403);

        $cabangId = $this->resolveCabangId($request->integer('cabang') ?: null);

        $queues = OrderQueue::with('meja')
            ->forCabang($cabangId)
            ->pending()
            ->orderBy('created_at')
            ->get();

        return view('kasir.queue.index', [
            'queues' => $queues,
            'cabangId' => $cabangId,
        ]);
    }

    public function approveQueue(int $id)
    {
        abort_unless(auth()->user()->can('kasir.queue.approve'), 403);

        try {
            $bill = $this->billService->approveQueue($id, auth()->id());
            return response()->json(['success' => true, 'bill_id' => $bill->id]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function rejectQueue(Request $request, int $id)
    {
        abort_unless(auth()->user()->can('kasir.queue.reject'), 403);

        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        try {
            $this->billService->rejectQueue($id, auth()->id(), $data['reason']);
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function detailMeja(int $mejaId)
    {
        abort_unless(auth()->user()->can('kasir.layout-meja.view'), 403);

        $meja = Meja::with(['bills' => fn ($q) => $q->whereIn('status', ['open', 'waiting_payment'])->with('items.item')])->findOrFail($mejaId);
        $bill = $meja->bills->first();

        $events = \App\Models\TableEvent::where('meja_id', $mejaId)->latest('created_at')->limit(10)->get();

        return response()->json([
            'meja'   => $meja,
            'bill'   => $bill,
            'events' => $events,
        ]);
    }

    public function tandaiTerisi(int $mejaId)
    {
        abort_unless(auth()->user()->can('kasir.meja.tandai-terisi'), 403);

        try {
            $bill = $this->billService->tandaiMejaTerisi($mejaId, auth()->id());
            return response()->json(['success' => true, 'bill_id' => $bill->id]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function tambahItem(Request $request, int $billId)
    {
        abort_unless(auth()->user()->can('kasir.bill.tambah-item'), 403);

        $data = $request->validate([
            'item_id' => ['required', 'exists:items,id'],
            'qty'     => ['required', 'numeric', 'min:0.01'],
            'varian'  => ['nullable', 'array'],
            'catatan' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $bill = $this->billService->addItemManual(
                $billId,
                (int) $data['item_id'],
                (float) $data['qty'],
                $data['varian'] ?? null,
                $data['catatan'] ?? null,
                auth()->id()
            );
            return response()->json(['success' => true, 'bill' => $bill]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function transfer(Request $request, int $billId)
    {
        abort_unless(auth()->user()->can('kasir.bill.transfer'), 403);

        $data = $request->validate(['meja_baru_id' => ['required', 'exists:mejas,id']]);

        try {
            $bill = $this->billService->transferMeja($billId, (int) $data['meja_baru_id'], auth()->id());
            return response()->json(['success' => true, 'bill' => $bill]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function printStruk(int $id)
    {
        abort_unless(auth()->user()->can('kasir.bill.print'), 403);

        $bill = Bill::with(['items.item', 'meja.cabang'])->findOrFail($id);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('kasir.bill.struk', ['bill' => $bill])
            ->setPaper([0, 0, 226.77, 800]); // 80mm thermal, tinggi elastis

        return $pdf->stream("struk-bill-{$bill->nomor_bill}.pdf");
    }

    public function bayar(Request $request, int $id)
    {
        abort_unless(auth()->user()->can('kasir.bill.bayar'), 403);

        $data = $request->validate([
            'payments'            => ['required', 'array', 'min:1'],
            'payments.*.metode'   => ['required', Rule::in(['tunai', 'transfer', 'qris'])],
            'payments.*.jumlah'   => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $bill = $this->billService->closeBill($id, $data, auth()->id());
            return response()->json(['success' => true, 'bill_id' => $bill->id]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Meja/bill genuinely per-outlet (Gudang Pusat tidak punya meja
     * customer), jadi beda dari pola "owner = semua cabang" di StokController
     * -- owner/admin_pusat TETAP harus resolve ke 1 outlet spesifik.
     */
    private function resolveCabangId(?int $requested): int
    {
        $user = auth()->user();

        if ($requested && $user->canAccessAllBranches()) {
            return $requested;
        }

        $sessionCabangId = session('active_cabang_id');
        if ($sessionCabangId) {
            return $sessionCabangId;
        }

        if ($user->canAccessAllBranches()) {
            return Cabang::aktif()->cabangSaja()->orderBy('id')->value('id');
        }

        return $user->defaultCabangId();
    }

    private function cabangPilihan()
    {
        $user = auth()->user();

        if ($user->canAccessAllBranches()) {
            return Cabang::aktif()->cabangSaja()->get();
        }

        return Cabang::aktif()->cabangSaja()->where('id', $user->defaultCabangId())->get();
    }
}
