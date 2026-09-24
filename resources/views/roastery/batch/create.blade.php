@extends('layouts.app')

@section('title', 'Batch Roasting Baru')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-cup-hot me-2 text-success"></i>Batch Roasting Baru</h5>
    <x-panduan-button slug="roastery-batch" />
</div>

<div class="card"><div class="card-body">
<form method="POST" action="{{ route('roastery.batch.store') }}">
    @csrf
    <div class="row g-3">
        <div class="col-12 col-md-4">
            <label class="form-label small fw-semibold">Tanggal *</label>
            <input type="date" name="tanggal" class="form-control @error('tanggal') is-invalid @enderror" value="{{ old('tanggal', now()->toDateString()) }}" required>
        </div>
        <div class="col-12 col-md-4">
            <label class="form-label small fw-semibold">Profile Roasting *</label>
            <select name="profile_id" id="profileId" class="form-select @error('profile_id') is-invalid @enderror" required>
                @foreach($profiles as $p)
                <option value="{{ $p->id }}" data-susut="{{ (float) $p->avg_susut_percent }}" @selected(old('profile_id') == $p->id)>{{ $p->nama }} (susut ~{{ (float) $p->avg_susut_percent }}%)</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-md-4">
            <label class="form-label small fw-semibold">Green Bean *</label>
            <select name="green_bean_item_id" id="greenItem" class="form-select @error('green_bean_item_id') is-invalid @enderror" required>
                @foreach($greenItems as $g)
                <option value="{{ $g->id }}" data-stok="{{ $stokGreen[$g->id] }}" @selected(old('green_bean_item_id') == $g->id)>{{ $g->nama_item }} — stok {{ (float) $stokGreen[$g->id] }} kg</option>
                @endforeach
            </select>
            @error('green_bean_item_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12 col-md-4">
            <label class="form-label small fw-semibold">Berat Green Bean (kg) * <x-tooltip key="roastery_batch.green_qty" /></label>
            <input type="number" step="0.001" min="0.001" name="green_qty_kg" id="greenQty" class="form-control @error('green_qty_kg') is-invalid @enderror" value="{{ old('green_qty_kg') }}" required>
            @error('green_qty_kg')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12 col-md-4">
            <label class="form-label small fw-semibold">Item Hasil (Roasted Curah) *</label>
            <select name="roasted_curah_item_id" class="form-select @error('roasted_curah_item_id') is-invalid @enderror" required>
                @foreach($curahItems as $c)
                <option value="{{ $c->id }}" @selected(old('roasted_curah_item_id') == $c->id)>{{ $c->nama_item }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-md-4">
            <label class="form-label small fw-semibold">Berat Roasted Aktual (kg) * <x-tooltip key="roastery_batch.roasted_qty" /></label>
            <input type="number" step="0.001" min="0" name="roasted_qty_kg" id="roastedQty" class="form-control @error('roasted_qty_kg') is-invalid @enderror" value="{{ old('roasted_qty_kg') }}" required>
            <div class="form-text" id="estimasiText"></div>
            @error('roasted_qty_kg')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12">
            <div class="alert alert-light border small mb-0" id="ringkasanLive">Isi berat green &amp; roasted untuk melihat susut/waste, yield, dan estimasi cost per kg.</div>
        </div>
        <div class="col-12">
            <label class="form-label small fw-semibold">Catatan</label>
            <textarea name="catatan" rows="2" class="form-control" maxlength="500">{{ old('catatan') }}</textarea>
        </div>
    </div>
    <div class="mt-3 d-flex gap-2">
        <button class="btn btn-primary btn-sm">Simpan Draft</button>
        <a href="{{ route('roastery.batch.index') }}" class="btn btn-outline-secondary btn-sm">Batal</a>
    </div>
</form>
</div></div>

@push('scripts')
<script>
(function () {
    var profile = document.getElementById('profileId'), green = document.getElementById('greenQty'),
        roasted = document.getElementById('roastedQty'), greenItem = document.getElementById('greenItem'),
        est = document.getElementById('estimasiText'), out = document.getElementById('ringkasanLive');
    var HARGA = @json($greenItems->mapWithKeys(fn ($g) => [$g->id => (float) $g->harga_beli_terakhir]));

    function hitung() {
        var g = parseFloat(green.value) || 0, r = parseFloat(roasted.value) || 0;
        var susut = parseFloat(profile.selectedOptions[0].dataset.susut) || 0;
        est.textContent = g > 0 ? 'Estimasi profile: ' + (g * (1 - susut / 100)).toFixed(3) + ' kg (bisa diedit sesuai kondisi aktual)' : '';
        if (g > 0 && r >= 0 && roasted.value !== '') {
            var waste = g - r, yieldP = r / g * 100, cost = g * (HARGA[greenItem.value] || 0);
            out.innerHTML = 'Susut/waste: <b>' + waste.toFixed(3) + ' kg</b> (' + (100 - yieldP).toFixed(2) + '%) · Yield: <b>' + yieldP.toFixed(2) + '%</b>' +
                (r > 0 ? ' · Estimasi cost/kg: <b>Rp ' + Math.round(cost / r).toLocaleString('id-ID') + '</b> (harga beli terakhir, angka final dari FIFO saat diselesaikan)' : '');
        }
    }
    [profile, green, roasted, greenItem].forEach(function (el) { el.addEventListener('input', hitung); el.addEventListener('change', hitung); });
    hitung();
})();
</script>
@endpush
@endsection
