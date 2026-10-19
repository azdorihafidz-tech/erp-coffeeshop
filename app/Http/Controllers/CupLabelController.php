<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\Order;
use App\Services\CupLabelService;
use Illuminate\Http\Request;

/**
 * Sprint Label Cup (2026-10-19) — data & halaman cetak label gelas.
 * Default respons JSON (dipakai overlay cetak di POS/kasir, termasuk
 * Bluetooth); ?format=html = halaman stiker siap cetak browser.
 */
class CupLabelController extends Controller
{
    public function __construct(private CupLabelService $service)
    {
    }

    public function order(Request $request, Order $order)
    {
        abort_unless(auth()->user()->can('label_cup.print'), 403);
        $this->authorizeCabang($order->cabang_id);

        return $this->respon($request, $this->service->untukOrder($order),
            route('label-cup.order', ['order' => $order, 'format' => 'html']));
    }

    public function bill(Request $request, Bill $bill)
    {
        abort_unless(auth()->user()->can('label_cup.print'), 403);
        $this->authorizeCabang($bill->cabang_id);

        $items = $request->filled('items')
            ? array_filter(array_map('intval', explode(',', (string) $request->items)))
            : null;

        return $this->respon($request, $this->service->untukBill($bill, $items),
            route('label-cup.bill', array_filter(['bill' => $bill, 'items' => $request->items, 'format' => 'html'])));
    }

    private function respon(Request $request, array $hasil, string $printUrl)
    {
        if ($request->get('format') === 'html') {
            return view('label-cup.print', ['hasil' => $hasil]);
        }

        return response()->json($hasil + ['print_url' => $printUrl]);
    }

    private function authorizeCabang(?int $cabangId): void
    {
        $user = auth()->user();
        if ($user->canAccessAllBranches()) {
            return;
        }
        $aktif = session('active_cabang_id') ?? $user->defaultCabangId();
        abort_if((int) $cabangId !== (int) $aktif, 403);
    }
}
