<?php

namespace App\Http\Controllers;

use App\Enums\GrindSize;
use App\Models\GrindingBatch;
use App\Models\Item;
use App\Services\GrindingBatchService;
use App\Services\RoastingBatchService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GrindingBatchController extends Controller
{
    public function __construct(private GrindingBatchService $service) {}

    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('grinding-batch.view'), 403);

        $query = GrindingBatch::with(['roastedItem', 'groundItem'])
            ->where('cabang_id', RoastingBatchService::roasteryCabangId());

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return view('roastery.grinding-batch.index', [
            'batches' => $query->orderByDesc('tanggal')->orderByDesc('id')->paginate(20)->withQueryString(),
        ]);
    }

    public function create()
    {
        abort_unless(auth()->user()->can('grinding-batch.create'), 403);

        $cabangId = RoastingBatchService::roasteryCabangId();
        $roastedItems = $this->roastedItems();

        return view('roastery.grinding-batch.create', [
            'roastedItems' => $roastedItems,
            'stokRoasted'  => $roastedItems->mapWithKeys(fn ($i) => [$i->id => $i->stokDiLokasi($cabangId)]),
            'grindSizes'   => GrindSize::cases(),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->can('grinding-batch.create'), 403);

        $data = $request->validate([
            'tanggal'           => ['required', 'date'],
            'roasted_item_id'   => ['required', Rule::in($this->roastedItems()->pluck('id')->all())],
            'roasted_qty_kg_in' => ['required', 'numeric', 'gt:0', 'max:9999'],
            'grind_size'        => ['required', Rule::in(array_column(GrindSize::cases(), 'value'))],
            'catatan'           => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $batch = $this->service->startBatch($data, auth()->id());
        } catch (\Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('grinding-batch.show', $batch)
            ->with('success', "Batch {$batch->kode_batch} tersimpan sebagai draft.");
    }

    public function show(GrindingBatch $grindingBatch)
    {
        abort_unless(auth()->user()->can('grinding-batch.view'), 403);
        abort_unless($grindingBatch->cabang_id === RoastingBatchService::roasteryCabangId(), 404);

        $grindingBatch->load(['roastedItem', 'groundItem', 'user']);

        return view('roastery.grinding-batch.show', ['batch' => $grindingBatch]);
    }

    public function complete(Request $request, GrindingBatch $grindingBatch)
    {
        abort_unless(auth()->user()->can('grinding-batch.selesaikan'), 403);
        abort_unless($grindingBatch->cabang_id === RoastingBatchService::roasteryCabangId(), 404);

        $data = $request->validate(['ground_qty_kg_out' => ['required', 'numeric', 'gt:0', 'max:9999']]);

        try {
            $this->service->completeBatch($grindingBatch->id, (float) $data['ground_qty_kg_out']);
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Batch {$grindingBatch->kode_batch} selesai — stok ground bertambah.");
    }

    public function batalkan(GrindingBatch $grindingBatch)
    {
        abort_unless(auth()->user()->can('grinding-batch.batalkan'), 403);
        abort_unless($grindingBatch->cabang_id === RoastingBatchService::roasteryCabangId(), 404);

        try {
            $this->service->batalkan($grindingBatch->id);
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('grinding-batch.index')->with('success', "Batch {$grindingBatch->kode_batch} dibatalkan.");
    }

    private function roastedItems()
    {
        return Item::aktif()->where('kode_item', 'like', 'RTB-CURAH-%')->orderBy('kode_item')->get();
    }
}
