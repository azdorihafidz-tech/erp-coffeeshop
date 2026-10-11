@extends('layouts.app')

@section('title', 'Tambah Bahan Baku / Kemasan')

@section('content')

<div class="d-flex flex-column flex-sm-row align-items-center justify-content-between gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-plus-circle me-2 text-primary"></i>Tambah Bahan Baku / Kemasan</h5>
    <div class="d-flex gap-2 align-items-center">
        <a href="{{ route('master.bahan-baku.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Kembali
        </a>
        <x-panduan-button slug="bahan-baku" />
    </div>
</div>

<form method="POST" action="{{ route('master.bahan-baku.store') }}">
@csrf
<div class="row g-4">
    <div class="col-12 col-lg-8">
        <div class="card mb-3">
            <div class="card-header fw-semibold">Informasi Dasar</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-4">
                        <label class="form-label small fw-semibold">Kode Item *</label>
                        <input type="text" name="kode_item" class="form-control @error('kode_item') is-invalid @enderror"
                            style="text-transform:uppercase" value="{{ old('kode_item') }}" placeholder="cth: BB-007" required>
                        @error('kode_item')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-8">
                        <label class="form-label small fw-semibold">Nama Item *</label>
                        <input type="text" name="nama_item" class="form-control @error('nama_item') is-invalid @enderror"
                            value="{{ old('nama_item') }}" required>
                        @error('nama_item')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label small fw-semibold">Tipe *</label>
                        <select name="tipe" class="form-select @error('tipe') is-invalid @enderror" required>
                            <option value="">— Pilih —</option>
                            <option value="bahan_baku" @selected(old('tipe')=='bahan_baku')>Bahan Baku</option>
                            <option value="kemasan" @selected(old('tipe')=='kemasan')>Kemasan</option>
                            <option value="tambahan_gratis" @selected(old('tipe')=='tambahan_gratis')>Tambahan Gratis (add-on POS)</option>
                        </select>
                        @error('tipe')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label small fw-semibold">Unit / Satuan *</label>
                        <input type="text" name="satuan" class="form-control @error('satuan') is-invalid @enderror"
                            value="{{ old('satuan') }}" placeholder="pcs / gram / ml / kg" required>
                        @error('satuan')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label small fw-semibold">Kategori</label>
                        <div class="input-group">
                            <select name="item_category_id" class="form-select">
                                <option value="">— Pilih —</option>
                                @foreach($categories as $cat)
                                <option value="{{ $cat->id }}" @selected(old('item_category_id')==$cat->id)>{{ $cat->nama_kategori }}</option>
                                @endforeach
                            </select>
                            <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalKategori" title="Tambah kategori baru">
                                <i class="bi bi-plus-lg"></i>
                            </button>
                        </div>
                        <div class="form-text" style="font-size:0.72rem">Klik <i class="bi bi-plus-lg"></i> untuk tambah kategori baru.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header fw-semibold">Harga &amp; Stok Minimum</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-semibold">Harga Beli / HPP</label>
                        <x-input-rupiah name="harga_beli_terakhir" :value="old('harga_beli_terakhir', 0)" />
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-semibold">Qty Minimum</label>
                        <input type="number" name="qty_minimum" class="form-control" min="0" step="0.001" value="{{ old('qty_minimum', 0) }}">
                    </div>
                </div>
            </div>
        </div>

        {{-- Sprint Fix Master Barang Lengkap (2026-10-11): pindahan dari /item/create --}}
        <div class="card mb-3">
            <div class="card-header fw-semibold">Jenis &amp; Pelacakan Stok</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-semibold">Jenis <span class="text-danger">*</span></label>
                        <select name="jenis" class="form-select @error('jenis') is-invalid @enderror" required>
                            <option value="bahan_baku" {{ old('jenis', 'bahan_baku') === 'bahan_baku' ? 'selected' : '' }}>Bahan Baku (konsumsi berulang)</option>
                            <option value="perlengkapan" {{ old('jenis') === 'perlengkapan' ? 'selected' : '' }}>Perlengkapan (asset pakai, mis. gelas)</option>
                        </select>
                        @error('jenis')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-6 d-flex align-items-end">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" name="track_stok" value="1" id="track_stok" {{ old('track_stok', true) ? 'checked' : '' }}>
                            <label class="form-check-label fw-semibold" for="track_stok">Lacak Stok</label>
                            <div class="form-text small">Matikan hanya untuk item yang tidak perlu dipantau stoknya (mis. tisu lepasan).</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <x-unit-beli-section />

        <div class="card mb-3">
            <div class="card-header fw-semibold">Stok Awal per Outlet (opsional) <x-tooltip key="master_bahan_baku.stok_awal" /></div>
            <div class="card-body">
                <div class="row g-2">
                    @foreach($cabangList as $cabang)
                    <div class="col-6 col-md-4">
                        <label class="form-label small">{{ $cabang->nama_cabang }}</label>
                        <input type="number" name="stok_awal[{{ $cabang->id }}]" class="form-control form-control-sm" min="0" step="0.001" placeholder="0">
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-4">
        <div class="card">
            <div class="card-body">
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" checked>
                    <label class="form-check-label fw-semibold" for="is_active">Aktif</label>
                </div>
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Simpan</button>
                    <a href="{{ route('master.bahan-baku.index') }}" class="btn btn-outline-secondary">Batal</a>
                </div>
            </div>
        </div>
    </div>
</div>
</form>

{{-- Sprint Fix Master Barang Lengkap (2026-10-11): modal Tambah Kategori inline. --}}
<x-kategori-item-modal />

@endsection
