<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\RoastingBatch;
use App\Models\RoastingProfile;
use App\Services\RoastingBatchService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoastingBatchController extends Controller
{
    public function __construct(private RoastingBatchService $service) {}

    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('roastery.batch.view'), 403);

        $query = RoastingBatch::with(['greenBean', 'roastedCurah', 'profile', 'user'])
            ->where('cabang_id', RoastingBatchService::roasteryCabangId());

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return view('roastery.batch.index', [
            'batches' => $query->orderByDesc('tanggal')->orderByDesc('id')->paginate(20)->withQueryString(),
        ]);
    }

    public function create()
    {
        abort_unless(auth()->user()->can('roastery.batch.create'), 403);

        $gp = RoastingBatchService::roasteryCabangId();

        return view('roastery.batch.create', [
            'profiles'    => RoastingProfile::aktif()->orderBy('avg_susut_percent')->get(),
            'greenItems'  => $this->greenItems(),
            'curahItems'  => $this->curahItems(),
            'stokGreen'   => $this->greenItems()->mapWithKeys(fn ($i) => [$i->id => $i->stokDiLokasi($gp)]),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->can('roastery.batch.create'), 403);

        $data = $request->validate([
            'tanggal'               => ['required', 'date'],
            'profile_id'            => ['required', Rule::exists('roasting_profiles', 'id')->where('is_active', true)],
            'green_bean_item_id'    => ['required', Rule::in($this->greenItems()->pluck('id')->all())],
            'green_qty_kg'          => ['required', 'numeric', 'gt:0', 'max:9999'],
            'roasted_curah_item_id' => ['required', Rule::in($this->curahItems()->pluck('id')->all())],
            'roasted_qty_kg'        => ['required', 'numeric', 'min:0', 'max:9999'],
            'catatan'               => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $batch = $this->service->createBatch($data, auth()->id());
        } catch (\Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('roastery.batch.show', $batch)
            ->with('success', "Batch {$batch->nomor_batch} tersimpan sebagai draft. Klik Selesaikan untuk memproses stok.");
    }

    public function show(RoastingBatch $batch)
    {
        abort_unless(auth()->user()->can('roastery.batch.view'), 403);
        abort_unless($batch->cabang_id === RoastingBatchService::roasteryCabangId(), 404);

        $batch->load(['greenBean', 'roastedCurah', 'profile', 'user', 'packs.itemPack']);

        return view('roastery.batch.show', [
            'batch'     => $batch,
            'packItems' => Item::aktif()->where('tipe', 'produk_jual')
                ->whereHas('category', fn ($q) => $q->where('kode_kategori', 'RTB'))
                ->orderBy('kode_item')->get(),
        ]);
    }

    public function complete(RoastingBatch $batch)
    {
        abort_unless(auth()->user()->can('roastery.batch.complete'), 403);
        abort_unless($batch->cabang_id === RoastingBatchService::roasteryCabangId(), 404);

        try {
            $this->service->completeBatch($batch->id);
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Batch {$batch->nomor_batch} selesai — stok green berkurang, stok roasted curah bertambah.");
    }

    public function cancel(RoastingBatch $batch)
    {
        abort_unless(auth()->user()->can('roastery.batch.cancel'), 403);
        abort_unless($batch->cabang_id === RoastingBatchService::roasteryCabangId(), 404);

        try {
            $this->service->cancelBatch($batch->id);
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('roastery.batch.index')->with('success', "Batch {$batch->nomor_batch} dibatalkan.");
    }

    public function pack(Request $request, RoastingBatch $batch)
    {
        abort_unless(auth()->user()->can('roastery.batch.pack'), 403);
        abort_unless($batch->cabang_id === RoastingBatchService::roasteryCabangId(), 404);

        $data = $request->validate([
            'packs'                     => ['required', 'array', 'min:1'],
            'packs.*.item_pack_id'      => ['required', 'exists:items,id'],
            'packs.*.qty_pack'          => ['nullable', 'integer', 'min:0', 'max:100000'],
            'packs.*.berat_per_pack_gr' => ['required', 'integer', 'min:1', 'max:10000'],
        ]);

        try {
            $this->service->packBatch($batch->id, $data['packs'], auth()->id());
        } catch (\Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pengemasan tersimpan — stok roasted curah berkurang, stok RTB pack bertambah di Gudang Pusat.');
    }

    private function greenItems()
    {
        return Item::aktif()->where('tipe', 'bahan_baku')
            ->whereHas('category', fn ($q) => $q->where('kode_kategori', 'GRB'))
            ->orderBy('nama_item')->get();
    }

    private function curahItems()
    {
        return Item::aktif()->where('kode_item', 'like', 'RTB-CURAH-%')->orderBy('kode_item')->get();
    }
}
