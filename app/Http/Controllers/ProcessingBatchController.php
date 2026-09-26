<?php

namespace App\Http\Controllers;

use App\Enums\ProcessingMethod;
use App\Models\Item;
use App\Models\ProcessingBatch;
use App\Services\ProcessingBatchService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProcessingBatchController extends Controller
{
    public function __construct(private ProcessingBatchService $service) {}

    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('processing-batch.view'), 403);

        $query = ProcessingBatch::with(['cherryItem', 'greenBean'])
            ->where('cabang_id', ProcessingBatchService::roasteryCabangId());

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return view('roastery.processing-batch.index', [
            'batches' => $query->orderByDesc('tanggal_mulai')->orderByDesc('id')->paginate(20)->withQueryString(),
        ]);
    }

    public function create()
    {
        abort_unless(auth()->user()->can('processing-batch.create'), 403);

        $gp = ProcessingBatchService::roasteryCabangId();

        return view('roastery.processing-batch.create', [
            'cherryItems' => $this->cherryItems(),
            'stokCherry'  => $this->cherryItems()->mapWithKeys(fn ($i) => [$i->id => $i->stokDiLokasi($gp)]),
            'methods'     => ProcessingMethod::cases(),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->can('processing-batch.create'), 403);

        $data = $request->validate([
            'tanggal_mulai'      => ['required', 'date'],
            'cherry_item_id'     => ['required', Rule::in($this->cherryItems()->pluck('id')->all())],
            'cherry_qty_kg'      => ['required', 'numeric', 'gt:0', 'max:99999'],
            'processing_method'  => ['required', Rule::in(array_column(ProcessingMethod::cases(), 'value'))],
            'catatan_umum'       => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $batch = $this->service->startBatch($data, auth()->id());
        } catch (\Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('processing-batch.show', $batch)
            ->with('success', "Batch {$batch->kode_batch} tersimpan sebagai draft. Klik Mulai Processing untuk potong stok cherry.");
    }

    public function show(ProcessingBatch $processingBatch)
    {
        abort_unless(auth()->user()->can('processing-batch.view'), 403);
        abort_unless($processingBatch->cabang_id === ProcessingBatchService::roasteryCabangId(), 404);

        $processingBatch->load(['cherryItem', 'greenBean', 'user']);

        return view('roastery.processing-batch.show', ['batch' => $processingBatch]);
    }

    public function mulai(ProcessingBatch $processingBatch)
    {
        abort_unless(auth()->user()->can('processing-batch.mulai'), 403);
        abort_unless($processingBatch->cabang_id === ProcessingBatchService::roasteryCabangId(), 404);

        try {
            $this->service->mulaiProcessing($processingBatch->id);
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Processing dimulai — stok cherry sudah dipotong.');
    }

    public function selesaiFermentasi(Request $request, ProcessingBatch $processingBatch)
    {
        abort_unless(auth()->user()->can('processing-batch.update-step'), 403);
        abort_unless($processingBatch->cabang_id === ProcessingBatchService::roasteryCabangId(), 404);

        $data = $request->validate([
            'fermentasi_suhu_celsius' => ['nullable', 'integer', 'min:0', 'max:60'],
            'fermentasi_catatan'      => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->service->selesaiFermentasi($processingBatch->id, $data);
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Fermentasi selesai — lanjut ke tahap Drying.');
    }

    public function selesaiDrying(Request $request, ProcessingBatch $processingBatch)
    {
        abort_unless(auth()->user()->can('processing-batch.update-step'), 403);
        abort_unless($processingBatch->cabang_id === ProcessingBatchService::roasteryCabangId(), 404);

        $data = $request->validate([
            'drying_catatan' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->service->selesaiDrying($processingBatch->id, $data);
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Drying selesai — lanjut ke tahap Hulling.');
    }

    public function selesaiHulling(Request $request, ProcessingBatch $processingBatch)
    {
        abort_unless(auth()->user()->can('processing-batch.update-step'), 403);
        abort_unless($processingBatch->cabang_id === ProcessingBatchService::roasteryCabangId(), 404);

        $data = $request->validate(['hulling_qty_kg' => ['required', 'numeric', 'gt:0', 'max:99999']]);

        try {
            $this->service->selesaiHulling($processingBatch->id, (float) $data['hulling_qty_kg']);
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Hulling selesai — lanjut ke tahap Sortir.');
    }

    public function selesaiSortir(Request $request, ProcessingBatch $processingBatch)
    {
        abort_unless(auth()->user()->can('processing-batch.selesaikan'), 403);
        abort_unless($processingBatch->cabang_id === ProcessingBatchService::roasteryCabangId(), 404);

        $data = $request->validate([
            'sortir_qty_kg'    => ['required', 'numeric', 'gt:0', 'max:99999'],
            'sortir_defect_kg' => ['required', 'numeric', 'min:0', 'max:99999'],
        ]);

        try {
            $this->service->selesaiSortir($processingBatch->id, (float) $data['sortir_qty_kg'], (float) $data['sortir_defect_kg']);
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Sortir selesai — green bean masuk stok RST001.');
    }

    public function batalkan(Request $request, ProcessingBatch $processingBatch)
    {
        abort_unless(auth()->user()->can('processing-batch.batalkan'), 403);
        abort_unless($processingBatch->cabang_id === ProcessingBatchService::roasteryCabangId(), 404);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        try {
            $this->service->batalkan($processingBatch->id, $data['reason'] ?? null);
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('processing-batch.index')->with('success', "Batch {$processingBatch->kode_batch} dibatalkan.");
    }

    /** Sengaja cuma cherry Arabika — 4 item Green Bean hasil (ItemGreenBeanSeeder) baru ada utk Arabika. */
    private function cherryItems()
    {
        return Item::aktif()->where('kode_item', 'BHK-ARABIKA-SDK')->get();
    }
}
