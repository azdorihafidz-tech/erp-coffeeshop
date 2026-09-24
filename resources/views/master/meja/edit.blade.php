@extends('layouts.app')

@section('title', 'Edit Meja')

@section('content')

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-pencil me-2 text-warning"></i>Edit Meja — {{ $meja->nama_meja }}</h5>
    <a href="{{ route('meja.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
</div>

@if($errors->any())
<div class="alert alert-danger">
    <ul class="mb-0">
        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
</div>
@endif

<div class="card mb-3">
    <div class="card-body">
        <form method="POST" action="{{ route('meja.update', $meja) }}">
            @csrf
            @method('PUT')

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Cabang <span class="text-danger">*</span></label>
                    <select name="cabang_id" class="form-select" required>
                        @foreach($cabangs as $cabang)
                        <option value="{{ $cabang->id }}" @selected(old('cabang_id', $meja->cabang_id) == $cabang->id)>{{ $cabang->nama_cabang }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Nomor Meja <span class="text-danger">*</span> <x-tooltip key="master_meja.nomor_meja" /></label>
                    <input type="number" name="nomor_meja" class="form-control" min="1" value="{{ old('nomor_meja', $meja->nomor_meja) }}" required>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Kapasitas <x-tooltip key="master_meja.kapasitas" /></label>
                    <input type="number" name="kapasitas" class="form-control" min="1" value="{{ old('kapasitas', $meja->kapasitas) }}">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Nama Meja <span class="text-danger">*</span> <x-tooltip key="master_meja.nama_meja" /></label>
                    <input type="text" name="nama_meja" class="form-control" maxlength="50" value="{{ old('nama_meja', $meja->nama_meja) }}" required>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Lokasi <span class="text-danger">*</span> <x-tooltip key="master_meja.lokasi" /></label>
                    <select name="lokasi" class="form-select">
                        @foreach(['indoor', 'outdoor', 'vip'] as $val)
                        <option value="{{ $val }}" class="text-capitalize" @selected(old('lokasi', $meja->lokasi) === $val)>{{ ucfirst($val) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Status <span class="text-danger">*</span></label>
                    <select name="status" class="form-select">
                        <option value="aktif" @selected(old('status', $meja->status) === 'aktif')>Aktif</option>
                        <option value="nonaktif" @selected(old('status', $meja->status) === 'nonaktif')>Nonaktif</option>
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label">Catatan</label>
                    <textarea name="catatan" class="form-control" rows="2">{{ old('catatan', $meja->catatan) }}</textarea>
                </div>
            </div>

            <div class="mt-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Simpan Perubahan</button>
                <a href="{{ route('meja.index') }}" class="btn btn-outline-secondary">Batal</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <h6 class="fw-bold mb-2"><i class="bi bi-qr-code me-2"></i>QR Code</h6>
        <div class="row g-3 align-items-center">
            <div class="col-md-8">
                <label class="form-label">Token QR (readonly) <x-tooltip key="master_meja.qr_token" /></label>
                <input type="text" class="form-control" value="{{ $meja->qr_token }}" readonly>
                <div class="form-text">Tipe: <span class="text-capitalize">{{ $meja->qr_type }}</span> <x-tooltip key="master_meja.qr_type" />@if($meja->qr_expires_at) — expired {{ $meja->qr_expires_at->translatedFormat('d M Y H:i') }}@endif</div>
            </div>
            <div class="col-md-4 d-flex gap-2">
                @can('master.meja.print-qr')
                <a href="{{ route('meja.print-qr', $meja) }}" class="btn btn-outline-success w-100"><i class="bi bi-download me-1"></i>Print QR</a>
                @endcan
            </div>
        </div>

        @can('master.meja.edit')
        <div class="alert alert-warning mt-3 mb-0 small">
            <i class="bi bi-exclamation-triangle me-1"></i>
            <strong>Regenerate QR</strong>: QR lama akan TIDAK berfungsi lagi, sticker yang sudah tertempel di meja perlu dicetak ulang.
            <form id="regen-form" method="POST" action="{{ route('meja.generate-qr', $meja) }}" class="d-inline">
                @csrf
                <button type="button" class="btn btn-sm btn-warning ms-2"
                    onclick="if(confirm('QR lama akan TIDAK berfungsi lagi. Yakin regenerate?')) document.getElementById('regen-form').submit()">
                    <i class="bi bi-arrow-repeat me-1"></i>Regenerate QR
                </button>
            </form>
        </div>
        @endcan
    </div>
</div>

@endsection
