<?php

namespace App\Http\Controllers;

use App\Enums\PackSize;
use App\Models\Item;
use App\Models\PackingBatch;
use App\Services\PackingBatchService;
use App\Services\RoastingBatchService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PackingBatchController extends Controller
{
    public function __construct(private PackingBatchService $service) {}

    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('packing-batch.view'), 403);

        $query = PackingBatch::with(['sourceItem', 'targetItem'])
            ->where('cabang_id', RoastingBatchService::roasteryCabangId());

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return view('roastery.packing-batch.index', [
            'batches' => $query->orderByDesc('tanggal')->orderByDesc('id')->paginate(20)->withQueryString(),
        ]);
    }

    public function create()
    {
        abort_unless(auth()->user()->can('packing-batch.create'), 403);

        $cabangId = RoastingBatchService::roasteryCabangId();
        $sourceItems = $this->sourceItems();

        return view('roastery.packing-batch.create', [
            'sourceItems' => $sourceItems,
            'stokSource'  => $sourceItems->mapWithKeys(fn ($i) => [$i->id => $i->stokDiLokasi($cabangId)]),
            'packSizes'   => PackSize::cases(),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->can('packing-batch.create'), 403);

        $data = $request->validate([
            'tanggal'           => ['required', 'date'],
            'source_type'       => ['required', Rule::in(['roasted_whole', 'roasted_ground'])],
            'source_item_id'    => ['required', Rule::in($this->sourceItems()->pluck('id')->all())],
            'source_qty_kg_in'  => ['required', 'numeric', 'gt:0', 'max:9999'],
            'target_pack_size'  => ['required', Rule::in(array_column(PackSize::cases(), 'value'))],
            'catatan'           => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $batch = $this->service->startBatch($data, auth()->id());
        } catch (\Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('packing-batch.show', $batch)
            ->with('success', "Batch {$batch->kode_batch} tersimpan sebagai draft.");
    }

    public function show(PackingBatch $packingBatch)
    {
        abort_unless(auth()->user()->can('packing-batch.view'), 403);
        abort_unless($packingBatch->cabang_id === RoastingBatchService::roasteryCabangId(), 404);

        $packingBatch->load(['sourceItem', 'targetItem', 'user']);

        return view('roastery.packing-batch.show', ['batch' => $packingBatch]);
    }

    public function complete(Request $request, PackingBatch $packingBatch)
    {
        abort_unless(auth()->user()->can('packing-batch.selesaikan'), 403);
        abort_unless($packingBatch->cabang_id === RoastingBatchService::roasteryCabangId(), 404);

        $data = $request->validate(['target_qty_pack' => ['required', 'integer', 'min:1', 'max:100000']]);

        try {
            $this->service->completeBatch($packingBatch->id, (int) $data['target_qty_pack']);
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Batch {$packingBatch->kode_batch} selesai — stok pack bertambah.");
    }

    public function batalkan(PackingBatch $packingBatch)
    {
        abort_unless(auth()->user()->can('packing-batch.batalkan'), 403);
        abort_unless($packingBatch->cabang_id === RoastingBatchService::roasteryCabangId(), 404);

        try {
            $this->service->batalkan($packingBatch->id);
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('packing-batch.index')->with('success', "Batch {$packingBatch->kode_batch} dibatalkan.");
    }

    private function sourceItems()
    {
        return Item::aktif()
            ->where(fn ($q) => $q->where('kode_item', 'like', 'RTB-CURAH-%')->orWhere('kode_item', 'like', 'GRD-ARB-%'))
            ->orderBy('kode_item')->get();
    }
}
