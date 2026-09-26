@extends('layouts.app')

@section('title', 'Grinding Batch Baru')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-gear-wide-connected me-2 text-success"></i>Grinding Batch Baru</h5>
    <x-panduan-button slug="grinding-batch" />
</div>

<div class="card"><div class="card-body">
<form method="POST" action="{{ route('grinding-batch.store') }}">
    @csrf
    <div class="row g-3">
        <div class="col-12 col-md-4">
            <label class="form-label small fw-semibold">Tanggal *</label>
            <input type="date" name="tanggal" class="form-control @error('tanggal') is-invalid @enderror" value="{{ old('tanggal', now()->toDateString()) }}" required>
            @error('tanggal')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12 col-md-4">
            <label class="form-label small fw-semibold">Roasted Bean (Whole) *</label>
            <select name="roasted_item_id" class="form-select @error('roasted_item_id') is-invalid @enderror" required>
                @forelse($roastedItems as $it)
                <option value="{{ $it->id }}" @selected(old('roasted_item_id') == $it->id)>{{ $it->nama_item }} — stok {{ (float) $stokRoasted[$it->id] }} kg</option>
                @empty
                <option value="">Belum ada stok roasted bean di RST001</option>
                @endforelse
            </select>
            @error('roasted_item_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12 col-md-4">
            <label class="form-label small fw-semibold">Qty Masuk (kg) *</label>
            <input type="number" step="0.001" min="0.001" name="roasted_qty_kg_in" class="form-control @error('roasted_qty_kg_in') is-invalid @enderror" value="{{ old('roasted_qty_kg_in') }}" required>
            @error('roasted_qty_kg_in')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-12">
            <label class="form-label small fw-semibold">Grind Size * <x-tooltip key="grinding.grind_size" /></label>
            <div class="d-flex flex-wrap gap-3">
                @foreach($grindSizes as $g)
                @php $supported = in_array($g->value, ['medium', 'fine', 'extra_fine']); @endphp
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="grind_size" id="gs-{{ $g->value }}" value="{{ $g->value }}" @disabled(! $supported) @checked(old('grind_size', 'medium') === $g->value)>
                    <label class="form-check-label {{ $supported ? '' : 'text-muted' }}" for="gs-{{ $g->value }}">{{ $g->label() }}{{ $supported ? '' : ' (belum tersedia)' }}</label>
                </div>
                @endforeach
            </div>
            @error('grind_size')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
        </div>

        <div class="col-12">
            <label class="form-label small fw-semibold">Catatan</label>
            <textarea name="catatan" rows="2" class="form-control" maxlength="500">{{ old('catatan') }}</textarea>
        </div>
    </div>
    <div class="mt-3 d-flex gap-2">
        <button class="btn btn-primary btn-sm">Simpan Draft</button>
        <a href="{{ route('grinding-batch.index') }}" class="btn btn-outline-secondary btn-sm">Batal</a>
    </div>
</form>
</div></div>
@endsection
