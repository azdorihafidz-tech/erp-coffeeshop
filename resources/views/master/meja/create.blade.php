@extends('layouts.app')

@section('title', 'Tambah Meja')

@section('content')

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-plus-circle me-2 text-success"></i>Tambah Meja</h5>
    <a href="{{ route('meja.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
</div>

@if($errors->any())
<div class="alert alert-danger">
    <ul class="mb-0">
        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
</div>
@endif

<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ route('meja.store') }}">
            @csrf

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Cabang <span class="text-danger">*</span></label>
                    <select name="cabang_id" class="form-select" required>
                        <option value="">-- Pilih Cabang --</option>
                        @foreach($cabangs as $cabang)
                        <option value="{{ $cabang->id }}" @selected(old('cabang_id') == $cabang->id)>{{ $cabang->nama_cabang }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Nomor Meja <span class="text-danger">*</span> <x-tooltip key="master_meja.nomor_meja" /></label>
                    <input type="number" name="nomor_meja" id="nomorMeja" class="form-control" min="1"
                           value="{{ old('nomor_meja') }}" required
                           oninput="document.getElementById('namaMeja').value = document.getElementById('namaMeja').value.trim() === '' || document.getElementById('namaMeja').dataset.auto === '1' ? (this.value ? 'Meja ' + this.value : '') : document.getElementById('namaMeja').value; document.getElementById('namaMeja').dataset.auto = '1';">
                </div>

                <div class="col-md-3">
                    <label class="form-label">Kapasitas <x-tooltip key="master_meja.kapasitas" /></label>
                    <input type="number" name="kapasitas" class="form-control" min="1" value="{{ old('kapasitas', 4) }}">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Nama Meja <span class="text-danger">*</span> <x-tooltip key="master_meja.nama_meja" /></label>
                    <input type="text" name="nama_meja" id="namaMeja" class="form-control" maxlength="50"
                           value="{{ old('nama_meja') }}" required placeholder="mis. Meja 5, Meja VIP 1">
                    <div class="form-text">Auto-terisi dari nomor meja, bisa diedit manual (mis. "Meja VIP 1").</div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Lokasi <span class="text-danger">*</span> <x-tooltip key="master_meja.lokasi" /></label>
                    <div class="btn-group w-100" role="group">
                        @foreach(['indoor' => 'bi-house-door', 'outdoor' => 'bi-tree', 'vip' => 'bi-star'] as $val => $icon)
                        <input type="radio" class="btn-check" name="lokasi" id="lokasi-{{ $val }}" value="{{ $val }}"
                               autocomplete="off" @checked(old('lokasi', 'indoor') === $val)>
                        <label class="btn btn-outline-success text-capitalize" for="lokasi-{{ $val }}">
                            <i class="bi {{ $icon }} me-1"></i>{{ $val }}
                        </label>
                        @endforeach
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label">Catatan</label>
                    <textarea name="catatan" class="form-control" rows="2">{{ old('catatan') }}</textarea>
                </div>
            </div>

            <div class="alert alert-info mt-3 mb-0 small">
                <i class="bi bi-info-circle me-1"></i>QR permanent akan otomatis di-generate setelah meja disimpan. Cetak sticker-nya lewat tombol <i class="bi bi-qr-code"></i> di halaman daftar meja.
            </div>

            <div class="mt-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Simpan Meja</button>
                <a href="{{ route('meja.index') }}" class="btn btn-outline-secondary">Batal</a>
            </div>
        </form>
    </div>
</div>

@endsection
