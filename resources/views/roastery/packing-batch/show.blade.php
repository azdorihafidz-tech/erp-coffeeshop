@extends('layouts.app')

@section('title', $batch->kode_batch)

@section('content')
@php $badge = ['draft' => 'secondary', 'selesai' => 'success', 'dibatalkan' => 'danger'][$batch->status]; @endphp
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-box-seam me-2 text-success"></i>{{ $batch->kode_batch }}
        <span class="badge bg-{{ $badge }} ms-2">{{ ucfirst($batch->status) }}</span></h5>
    <div class="d-flex gap-2 align-items-center">
        <x-panduan-button slug="packing-batch" />
        <a href="{{ route('packing-batch.index') }}" class="btn btn-outline-secondary btn-sm">Kembali</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-7">
        <div class="card"><div class="card-body">
            <table class="table table-sm mb-0">
                <tr><th style="width:220px">Tanggal</th><td>{{ $batch->tanggal->format('d/m/Y') }}</td></tr>
                <tr><th>Sumber</th><td>{{ $batch->sourceItem->nama_item }} — {{ number_format($batch->source_qty_kg_in, 3, ',', '.') }} kg</td></tr>
                <tr><th>Ukuran Pack</th><td>{{ $batch->target_pack_size->label() }}</td></tr>
                @if($batch->status === 'selesai')
                <tr><th>Item Pack</th><td>{{ $batch->targetItem->nama_item }}</td></tr>
                <tr><th>Qty Pack Dihasilkan</th><td>{{ $batch->target_qty_pack }} pack</td></tr>
                <tr><th>Waste</th><td>{{ number_format($batch->waste_kg, 3, ',', '.') }} kg</td></tr>
                <tr><th>Cost per Pack <x-tooltip key="packing-batch.cost_per_pack" /></th><td>Rp {{ number_format($batch->cost_per_pack, 0, ',', '.') }}</td></tr>
                <tr><th>Harga Jual</th><td>Rp {{ number_format($batch->targetItem->harga_jual, 0, ',', '.') }}</td></tr>
                @endif
                @if($batch->catatan)<tr><th>Catatan</th><td>{{ $batch->catatan }}</td></tr>@endif
                <tr><th>Dibuat oleh</th><td>{{ $batch->user->name }}</td></tr>
            </table>
        </div></div>
    </div>

    <div class="col-12 col-lg-5">
        <div class="card"><div class="card-body">
            @if($batch->status === 'draft')
                @can('packing-batch.selesaikan')
                <form method="POST" action="{{ route('packing-batch.complete', $batch) }}" class="mb-2">
                    @csrf
                    <label class="form-label small fw-semibold">Qty Pack Hasil *</label>
                    <input type="number" min="1" name="target_qty_pack" class="form-control mb-2" required>
                    <div class="form-text mb-2">Maks {{ floor($batch->source_qty_kg_in / $batch->target_pack_size->kg()) }} pack (dari {{ (float) $batch->source_qty_kg_in }} kg ÷ {{ $batch->target_pack_size->label() }}).</div>
                    <button class="btn btn-success w-100">Selesaikan — Stok Pack Masuk</button>
                </form>
                @endcan
                @can('packing-batch.batalkan')
                <form method="POST" action="{{ route('packing-batch.batalkan', $batch) }}" onsubmit="return confirm('Batalkan batch ini?')">
                    @csrf <button class="btn btn-outline-danger btn-sm w-100">Batalkan</button>
                </form>
                @endcan
            @elseif($batch->status === 'selesai')
                <div class="text-success small"><i class="bi bi-check-circle-fill me-1"></i>Stok pack sudah masuk RST001, siap dijual.</div>
            @else
                <div class="text-muted small">Batch ini sudah dibatalkan.</div>
            @endif
        </div></div>
    </div>
</div>
@endsection
