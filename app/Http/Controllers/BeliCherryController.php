<?php

namespace App\Http\Controllers;

use App\Models\CherryPurchase;
use App\Models\Item;
use App\Models\Petani;
use App\Services\CherryPurchaseService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BeliCherryController extends Controller
{
    public function __construct(private CherryPurchaseService $service) {}

    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('beli-cherry.view'), 403);

        $query = CherryPurchase::with(['petani', 'cherryItem'])
            ->where('cabang_id', CherryPurchaseService::roasteryCabangId());

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return view('roastery.beli-cherry.index', [
            'purchases' => $query->orderByDesc('tanggal')->orderByDesc('id')->paginate(20)->withQueryString(),
        ]);
    }

    public function create()
    {
        abort_unless(auth()->user()->can('beli-cherry.create'), 403);

        return view('roastery.beli-cherry.create', [
            'petanis'     => Petani::aktif()->orderBy('nama')->get(),
            'cherryItems' => $this->cherryItems(),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->can('beli-cherry.create'), 403);

        $data = $request->validate([
            'tanggal'        => ['required', 'date'],
            'petani_id'      => ['required', Rule::exists('petani', 'id')->where('is_active', true)],
            'cherry_item_id' => ['required', Rule::in($this->cherryItems()->pluck('id')->all())],
            'jenis_buah'     => ['required', Rule::in(['arabika', 'robusta'])],
            'qty_kg'         => ['required', 'numeric', 'gt:0', 'max:99999'],
            'harga_per_kg'   => ['required', 'numeric', 'min:0'],
            'kualitas_grade' => ['nullable', Rule::in(['A', 'B', 'C'])],
            'catatan'        => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $cp = $this->service->createDraft($data, auth()->id());
        } catch (\Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('beli-cherry.show', $cp)
            ->with('success', "Transaksi {$cp->kode_transaksi} tersimpan sebagai draft.");
    }

    public function show(CherryPurchase $beliCherry)
    {
        abort_unless(auth()->user()->can('beli-cherry.view'), 403);
        abort_unless($beliCherry->cabang_id === CherryPurchaseService::roasteryCabangId(), 404);

        $beliCherry->load(['petani', 'cherryItem', 'user', 'disetujuiOleh', 'diterimaOleh']);

        return view('roastery.beli-cherry.show', ['cp' => $beliCherry]);
    }

    public function setujui(CherryPurchase $beliCherry)
    {
        abort_unless(auth()->user()->can('beli-cherry.edit'), 403);
        abort_unless($beliCherry->cabang_id === CherryPurchaseService::roasteryCabangId(), 404);

        try {
            $this->service->setujui($beliCherry->id, auth()->id());
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Transaksi {$beliCherry->kode_transaksi} disetujui.");
    }

    public function terima(Request $request, CherryPurchase $beliCherry)
    {
        abort_unless(auth()->user()->can('beli-cherry.terima'), 403);
        abort_unless($beliCherry->cabang_id === CherryPurchaseService::roasteryCabangId(), 404);

        $data = $request->validate([
            'qty_terima_kg' => ['required', 'numeric', 'gt:0', 'max:99999'],
        ]);

        try {
            $this->service->terima($beliCherry->id, (float) $data['qty_terima_kg'], auth()->id());
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Buah cherry diterima — stok RST001 bertambah {$data['qty_terima_kg']} kg.");
    }

    public function batalkan(CherryPurchase $beliCherry)
    {
        abort_unless(auth()->user()->can('beli-cherry.batalkan'), 403);
        abort_unless($beliCherry->cabang_id === CherryPurchaseService::roasteryCabangId(), 404);

        try {
            $this->service->batalkan($beliCherry->id);
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('beli-cherry.index')->with('success', "Transaksi {$beliCherry->kode_transaksi} dibatalkan.");
    }

    private function cherryItems()
    {
        return Item::aktif()->where('kode_item', 'like', 'BHK-%')->orderBy('kode_item')->get();
    }
}
