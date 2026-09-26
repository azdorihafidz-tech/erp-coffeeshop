@extends('layouts.app')

@section('title', 'Packing Batch Baru')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-box-seam me-2 text-success"></i>Packing Batch Baru</h5>
    <x-panduan-button slug="packing-batch" />
</div>

<div class="card"><div class="card-body">
<form method="POST" action="{{ route('packing-batch.store') }}">
    @csrf
    <div class="row g-3">
        <div class="col-12 col-md-4">
            <label class="form-label small fw-semibold">Tanggal *</label>
            <input type="date" name="tanggal" class="form-control @error('tanggal') is-invalid @enderror" value="{{ old('tanggal', now()->toDateString()) }}" required>
            @error('tanggal')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-12">
            <label class="form-label small fw-semibold">Jenis Sumber *</label>
            <div class="d-flex gap-3">
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="source_type" id="st-whole" value="roasted_whole" @checked(old('source_type', 'roasted_whole') === 'roasted_whole')>
                    <label class="form-check-label" for="st-whole">Roasted Whole Bean (curah)</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="source_type" id="st-ground" value="roasted_ground" @checked(old('source_type') === 'roasted_ground')>
                    <label class="form-check-label" for="st-ground">Roasted Ground</label>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6">
            <label class="form-label small fw-semibold">Item Sumber *</label>
            <select name="source_item_id" class="form-select @error('source_item_id') is-invalid @enderror" required>
                @forelse($sourceItems as $it)
                <option value="{{ $it->id }}" @selected(old('source_item_id') == $it->id)>{{ $it->nama_item }} — stok {{ (float) $stokSource[$it->id] }} kg</option>
                @empty
                <option value="">Belum ada stok roasted/ground di RST001</option>
                @endforelse
            </select>
            @error('source_item_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12 col-md-6">
            <label class="form-label small fw-semibold">Qty Sumber Masuk (kg) *</label>
            <input type="number" step="0.001" min="0.001" name="source_qty_kg_in" class="form-control @error('source_qty_kg_in') is-invalid @enderror" value="{{ old('source_qty_kg_in') }}" required>
            @error('source_qty_kg_in')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-12">
            <label class="form-label small fw-semibold">Ukuran Pack * <x-tooltip key="packing-batch.target_pack_size" /></label>
            <div class="d-flex flex-wrap gap-3">
                @foreach($packSizes as $s)
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="target_pack_size" id="ps-{{ $s->value }}" value="{{ $s->value }}" @checked(old('target_pack_size', '250g') === $s->value)>
                    <label class="form-check-label" for="ps-{{ $s->value }}">{{ $s->label() }}</label>
                </div>
                @endforeach
            </div>
            @error('target_pack_size')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
        </div>

        <div class="col-12">
            <label class="form-label small fw-semibold">Catatan</label>
            <textarea name="catatan" rows="2" class="form-control" maxlength="500">{{ old('catatan') }}</textarea>
        </div>
    </div>
    <div class="mt-3 d-flex gap-2">
        <button class="btn btn-primary btn-sm">Simpan Draft</button>
        <a href="{{ route('packing-batch.index') }}" class="btn btn-outline-secondary btn-sm">Batal</a>
    </div>
</form>
</div></div>
@endsection
