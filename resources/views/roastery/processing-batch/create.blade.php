@extends('layouts.app')

@section('title', 'Processing Batch Baru')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-droplet-half me-2 text-success"></i>Processing Batch Baru</h5>
    <x-panduan-button slug="processing-batch" />
</div>

<div class="card"><div class="card-body">
<form method="POST" action="{{ route('processing-batch.store') }}">
    @csrf
    <div class="row g-3">
        <div class="col-12 col-md-4">
            <label class="form-label small fw-semibold">Tanggal Mulai *</label>
            <input type="date" name="tanggal_mulai" class="form-control @error('tanggal_mulai') is-invalid @enderror" value="{{ old('tanggal_mulai', now()->toDateString()) }}" required>
            @error('tanggal_mulai')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12 col-md-4">
            <label class="form-label small fw-semibold">Cherry (Item) *</label>
            <select name="cherry_item_id" class="form-select @error('cherry_item_id') is-invalid @enderror" required>
                @forelse($cherryItems as $it)
                <option value="{{ $it->id }}" @selected(old('cherry_item_id') == $it->id)>{{ $it->nama_item }} — stok {{ (float) $stokCherry[$it->id] }} kg</option>
                @empty
                <option value="">Belum ada stok cherry Arabika di RST001</option>
                @endforelse
            </select>
            @error('cherry_item_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <div class="form-text">Processing batch untuk sekarang hanya melayani cherry Arabika (4 SKU green bean output baru tersedia utk Arabika).</div>
        </div>
        <div class="col-12 col-md-4">
            <label class="form-label small fw-semibold">Qty Cherry (kg) *</label>
            <input type="number" step="0.001" min="0.001" name="cherry_qty_kg" class="form-control @error('cherry_qty_kg') is-invalid @enderror" value="{{ old('cherry_qty_kg') }}" required>
            @error('cherry_qty_kg')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-12">
            <label class="form-label small fw-semibold">Processing Method * <x-tooltip key="processing-batch.processing_method" /></label>
            <div class="d-flex flex-wrap gap-3">
                @foreach($methods as $m)
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="processing_method" id="method-{{ $m->value }}" value="{{ $m->value }}" @checked(old('processing_method', 'washed') === $m->value) required>
                    <label class="form-check-label" for="method-{{ $m->value }}">{{ $m->label() }}{{ $m->butuhFermentasi() ? ' (via Fermentasi)' : '' }}</label>
                </div>
                @endforeach
            </div>
            @error('processing_method')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
        </div>

        <div class="col-12">
            <label class="form-label small fw-semibold">Catatan</label>
            <textarea name="catatan_umum" rows="2" class="form-control" maxlength="500">{{ old('catatan_umum') }}</textarea>
        </div>
    </div>
    <div class="mt-3 d-flex gap-2">
        <button class="btn btn-primary btn-sm">Simpan Draft</button>
        <a href="{{ route('processing-batch.index') }}" class="btn btn-outline-secondary btn-sm">Batal</a>
    </div>
</form>
</div></div>
@endsection
