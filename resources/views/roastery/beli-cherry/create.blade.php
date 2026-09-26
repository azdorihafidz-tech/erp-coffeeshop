@extends('layouts.app')

@section('title', 'Beli Buah Kopi Baru')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-basket3 me-2 text-success"></i>Beli Buah Kopi Baru</h5>
    <x-panduan-button slug="beli-cherry" />
</div>

<div class="card"><div class="card-body">
<form method="POST" action="{{ route('beli-cherry.store') }}">
    @csrf
    <div class="row g-3">
        <div class="col-12 col-md-4">
            <label class="form-label small fw-semibold">Tanggal *</label>
            <input type="date" name="tanggal" class="form-control @error('tanggal') is-invalid @enderror" value="{{ old('tanggal', now()->toDateString()) }}" required>
            @error('tanggal')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12 col-md-4">
            <label class="form-label small fw-semibold">Petani *</label>
            <select name="petani_id" class="form-select @error('petani_id') is-invalid @enderror" required>
                <option value="">-- Pilih Petani --</option>
                @foreach($petanis as $p)
                <option value="{{ $p->id }}" @selected(old('petani_id') == $p->id)>{{ $p->nama }} ({{ $p->kode_petani }}){{ $p->nama_kebun ? ' — '.$p->nama_kebun : '' }}</option>
                @endforeach
            </select>
            @error('petani_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12 col-md-4">
            <label class="form-label small fw-semibold">Jenis Buah * <x-tooltip key="beli-cherry.jenis_buah" /></label>
            <select name="jenis_buah" id="jenisBuah" class="form-select @error('jenis_buah') is-invalid @enderror" required>
                <option value="arabika" @selected(old('jenis_buah', 'arabika') === 'arabika')>Arabika</option>
                <option value="robusta" @selected(old('jenis_buah') === 'robusta')>Robusta</option>
            </select>
            @error('jenis_buah')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-12">
            <label class="form-label small fw-semibold">Item Cherry (otomatis sesuai jenis buah)</label>
            <select name="cherry_item_id" id="cherryItemId" class="form-select @error('cherry_item_id') is-invalid @enderror" required>
                @foreach($cherryItems as $it)
                <option value="{{ $it->id }}" data-jenis="{{ str_contains($it->kode_item, 'ARABIKA') ? 'arabika' : 'robusta' }}" @selected(old('cherry_item_id') == $it->id)>{{ $it->nama_item }}</option>
                @endforeach
            </select>
            @error('cherry_item_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-12 col-md-4">
            <label class="form-label small fw-semibold">Qty Buah (kg) *</label>
            <input type="number" step="0.001" min="0.001" name="qty_kg" class="form-control @error('qty_kg') is-invalid @enderror" value="{{ old('qty_kg') }}" required>
            @error('qty_kg')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12 col-md-4">
            <label class="form-label small fw-semibold">Harga per kg (Rp) *</label>
            <input type="number" step="1" min="0" name="harga_per_kg" class="form-control @error('harga_per_kg') is-invalid @enderror" value="{{ old('harga_per_kg') }}" required>
            @error('harga_per_kg')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12 col-md-4">
            <label class="form-label small fw-semibold">Grade Kualitas <x-tooltip key="beli-cherry.kualitas_grade" /></label>
            <select name="kualitas_grade" class="form-select @error('kualitas_grade') is-invalid @enderror">
                <option value="">-- Tidak diisi --</option>
                @foreach(['A', 'B', 'C'] as $g)
                <option value="{{ $g }}" @selected(old('kualitas_grade') === $g)>Grade {{ $g }}</option>
                @endforeach
            </select>
            @error('kualitas_grade')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-12">
            <div class="alert alert-light border small mb-0" id="totalPreview">Isi qty & harga untuk melihat estimasi total.</div>
        </div>

        <div class="col-12">
            <label class="form-label small fw-semibold">Catatan</label>
            <textarea name="catatan" rows="2" class="form-control" maxlength="500">{{ old('catatan') }}</textarea>
        </div>
    </div>
    <div class="mt-3 d-flex gap-2">
        <button class="btn btn-primary btn-sm">Simpan Draft</button>
        <a href="{{ route('beli-cherry.index') }}" class="btn btn-outline-secondary btn-sm">Batal</a>
    </div>
</form>
</div></div>

@push('scripts')
<script>
(function () {
    var jenis = document.getElementById('jenisBuah'), cherryItem = document.getElementById('cherryItemId');
    var qty = document.querySelector('[name="qty_kg"]'), harga = document.querySelector('[name="harga_per_kg"]');
    var out = document.getElementById('totalPreview');

    function syncItem() {
        var opts = cherryItem.querySelectorAll('option');
        opts.forEach(function (o) { if (o.dataset.jenis === jenis.value) cherryItem.value = o.value; });
    }
    function hitung() {
        var q = parseFloat(qty.value) || 0, h = parseFloat(harga.value) || 0;
        out.textContent = (q > 0 && h > 0) ? 'Estimasi total: Rp ' + Math.round(q * h).toLocaleString('id-ID') : 'Isi qty & harga untuk melihat estimasi total.';
    }
    jenis.addEventListener('change', syncItem);
    [qty, harga].forEach(function (el) { el.addEventListener('input', hitung); });
    syncItem(); hitung();
})();
</script>
@endpush
@endsection
