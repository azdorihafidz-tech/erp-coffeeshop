@extends('layouts.app')

@section('title', 'Batch ' . $batch->nomor_batch)

@section('content')
@php $badge = ['draft' => 'secondary', 'completed' => 'success', 'cancelled' => 'danger'][$batch->status]; @endphp
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-cup-hot me-2 text-success"></i>{{ $batch->nomor_batch }}
        <span class="badge bg-{{ $badge }} ms-2">{{ ['draft' => 'Draft', 'completed' => 'Selesai', 'cancelled' => 'Dibatalkan'][$batch->status] }}</span></h5>
    <div class="d-flex gap-2 align-items-center">
        <x-panduan-button slug="roastery-batch" />
        <a href="{{ route('roastery.batch.index') }}" class="btn btn-outline-secondary btn-sm">Kembali</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-7">
        <div class="card"><div class="card-body">
            <table class="table table-sm mb-0">
                <tr><th style="width:40%">Tanggal</th><td>{{ $batch->tanggal->format('d/m/Y') }}</td></tr>
                <tr><th>Diinput oleh</th><td>{{ $batch->user->name }}</td></tr>
                <tr><th>Profile</th><td>{{ $batch->profile->nama }} (susut ~{{ (float) $batch->profile->avg_susut_percent }}%)</td></tr>
                <tr><th>Green bean</th><td>{{ $batch->greenBean->nama_item }} — {{ number_format($batch->green_qty_kg, 3, ',', '.') }} kg</td></tr>
                <tr><th>Sumber Green Bean</th><td>
                    @if($batch->green_bean_source === 'in_house')
                        In-House @if($batch->processingBatch) — <a href="{{ route('processing-batch.show', $batch->processingBatch) }}">{{ $batch->processingBatch->kode_batch }}</a> @endif
                    @elseif($batch->green_bean_source === 'bought')
                        Beli Langsung @if($batch->pembelian) — {{ $batch->pembelian->nomor_po }} @endif
                    @else
                        Stok Sedia / Manual
                    @endif
                </td></tr>
                <tr><th>Hasil (curah)</th><td>{{ $batch->roastedCurah->nama_item }} — {{ number_format($batch->roasted_qty_kg, 3, ',', '.') }} kg</td></tr>
                <tr><th>Susut / waste <x-tooltip key="roastery_batch.waste" /></th><td>{{ number_format($batch->waste_qty_kg, 3, ',', '.') }} kg</td></tr>
                <tr><th>Yield <x-tooltip key="roastery_batch.yield_rate" /></th><td>{{ number_format($batch->yield_rate_percent, 2, ',', '.') }}%</td></tr>
                @if($batch->status === 'completed')
                <tr><th>Cost awal (green)</th><td>Rp {{ number_format($batch->cost_awal, 0, ',', '.') }}</td></tr>
                <tr><th>Cost per kg roasted <x-tooltip key="roastery_batch.cost_per_kg" /></th><td>Rp {{ number_format($batch->cost_per_kg_roasted, 0, ',', '.') }}</td></tr>
                @endif
                @if($batch->catatan)<tr><th>Catatan</th><td>{{ $batch->catatan }}</td></tr>@endif
            </table>
        </div></div>

        @if($batch->status === 'draft')
        <div class="card mt-3"><div class="card-body d-flex flex-wrap gap-2 align-items-center">
            <div class="small text-muted me-auto">Draft belum menggerakkan stok. Klik <b>Selesaikan</b> untuk mengurangi stok green &amp; menambah stok roasted curah.</div>
            @can('roastery.batch.complete')
            <form method="POST" action="{{ route('roastery.batch.complete', $batch) }}" onsubmit="return confirm('Selesaikan batch? Stok akan berubah.')">
                @csrf <button class="btn btn-success btn-sm">Selesaikan</button>
            </form>
            @endcan
            @can('roastery.batch.cancel')
            <form method="POST" action="{{ route('roastery.batch.cancel', $batch) }}" onsubmit="return confirm('Batalkan draft ini?')">
                @csrf <button class="btn btn-outline-danger btn-sm">Batalkan</button>
            </form>
            @endcan
        </div></div>
        @endif
    </div>

    <div class="col-12 col-lg-5">
        @if($batch->status === 'completed')
        <div class="card"><div class="card-header fw-semibold small">Pengemasan <x-tooltip key="roastery_batch.pengemasan" /></div>
        <div class="card-body">
            <div class="small mb-2">Sisa curah belum dikemas: <b>{{ number_format($batch->sisa_curah_kg, 3, ',', '.') }} kg</b></div>
            @can('roastery.batch.pack')
            <form method="POST" action="{{ route('roastery.batch.pack', $batch) }}">
                @csrf
                @foreach($packItems as $i => $it)
                @php $gram = str_contains($it->kode_item, '001') ? 250 : (str_contains($it->kode_item, '002') ? 500 : 1000); @endphp
                <div class="row g-2 align-items-center mb-2">
                    <div class="col-6 small">{{ $it->nama_item }}
                        <input type="hidden" name="packs[{{ $i }}][item_pack_id]" value="{{ $it->id }}"></div>
                    <div class="col-3"><input type="number" min="0" name="packs[{{ $i }}][qty_pack]" class="form-control form-control-sm" placeholder="pack"></div>
                    <div class="col-3"><input type="number" min="1" name="packs[{{ $i }}][berat_per_pack_gr]" class="form-control form-control-sm" value="{{ $gram }}" title="gram per pack"></div>
                </div>
                @endforeach
                <div class="form-text mb-2">Kolom kanan = gram per pack. Stok RTB pack bertambah di Gudang Pusat.</div>
                <button class="btn btn-primary btn-sm">Simpan Pengemasan</button>
            </form>
            @endcan

            @if($batch->packs->isNotEmpty())
            <hr>
            <div class="small fw-semibold mb-1">Riwayat pengemasan</div>
            <table class="table table-sm small mb-0">
                @foreach($batch->packs as $pk)
                <tr><td>{{ $pk->itemPack->nama_item }}</td><td class="text-end">{{ $pk->qty_pack }} × {{ $pk->berat_per_pack_gr }} g = {{ (float) $pk->total_berat_kg }} kg</td></tr>
                @endforeach
            </table>
            @endif
        </div></div>
        @endif
    </div>
</div>
@endsection
