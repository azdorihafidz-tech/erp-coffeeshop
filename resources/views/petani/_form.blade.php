@php $p = $petani ?? null; @endphp
<div class="row g-3">
    <div class="col-12 col-md-4">
        <label class="form-label fw-semibold">Kode Petani <span class="text-danger">*</span></label>
        <input type="text" name="kode_petani" class="form-control @error('kode_petani') is-invalid @enderror"
            value="{{ old('kode_petani', $p->kode_petani ?? $kodeHint ?? '') }}" placeholder="{{ $kodeHint ?? '' }}">
        @error('kode_petani')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12 col-md-8">
        <label class="form-label fw-semibold">Nama Petani <span class="text-danger">*</span></label>
        <input type="text" name="nama" class="form-control @error('nama') is-invalid @enderror"
            value="{{ old('nama', $p->nama ?? '') }}" required>
        @error('nama')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12 col-md-6">
        <label class="form-label fw-semibold">Telepon</label>
        <input type="text" name="telepon" class="form-control @error('telepon') is-invalid @enderror"
            value="{{ old('telepon', $p->telepon ?? '') }}" placeholder="08xxxxxxxxxx">
        @error('telepon')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12 col-md-6">
        <label class="form-label fw-semibold">Nama Kebun <x-tooltip key="petani.nama_kebun" /></label>
        <input type="text" name="nama_kebun" class="form-control @error('nama_kebun') is-invalid @enderror"
            value="{{ old('nama_kebun', $p->nama_kebun ?? '') }}" placeholder="Kebun Sidikalang Atas">
        @error('nama_kebun')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12">
        <label class="form-label fw-semibold">Alamat</label>
        <textarea name="alamat" rows="2" class="form-control @error('alamat') is-invalid @enderror">{{ old('alamat', $p->alamat ?? '') }}</textarea>
        @error('alamat')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12 col-md-6">
        <label class="form-label fw-semibold">Koordinat Lat <x-tooltip key="petani.koordinat" /></label>
        <input type="number" step="0.0000001" name="koordinat_lat" class="form-control @error('koordinat_lat') is-invalid @enderror"
            value="{{ old('koordinat_lat', $p->koordinat_lat ?? '') }}" placeholder="-6.1754">
        @error('koordinat_lat')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12 col-md-6">
        <label class="form-label fw-semibold">Koordinat Lng</label>
        <input type="number" step="0.0000001" name="koordinat_lng" class="form-control @error('koordinat_lng') is-invalid @enderror"
            value="{{ old('koordinat_lng', $p->koordinat_lng ?? '') }}" placeholder="106.8272">
        @error('koordinat_lng')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12">
        <label class="form-label fw-semibold">Catatan</label>
        <textarea name="catatan" rows="2" class="form-control @error('catatan') is-invalid @enderror" maxlength="500">{{ old('catatan', $p->catatan ?? '') }}</textarea>
        @error('catatan')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>
