@extends('layouts.app')

@section('title', 'QR Meja Temporary')

@section('content')

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-clock-history me-2 text-success"></i>Buat QR Meja Temporary</h5>
    <a href="{{ route('meja.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
</div>

@if($errors->any())
<div class="alert alert-danger">
    <ul class="mb-0">
        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
</div>
@endif

<div class="alert alert-info small">
    <i class="bi bi-info-circle me-1"></i>Untuk kebutuhan sementara (event, area extra, booth) — meja ini otomatis nonaktif setelah tanggal expired yang ditentukan.
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ route('meja.temporary.store') }}">
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
                    <label class="form-label">Nomor Meja <span class="text-danger">*</span></label>
                    <input type="number" name="nomor_meja" class="form-control" min="1" value="{{ old('nomor_meja') }}" required>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Kapasitas</label>
                    <input type="number" name="kapasitas" class="form-control" min="1" value="{{ old('kapasitas') }}">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Nama Meja <span class="text-danger">*</span></label>
                    <input type="text" name="nama_meja" class="form-control" maxlength="50" value="{{ old('nama_meja') }}" required placeholder="mis. Reserved Event ABC">
                </div>

                <div class="col-md-6">
                    <label class="form-label">QR Berlaku Sampai <span class="text-danger">*</span></label>
                    <input type="datetime-local" name="qr_expires_at" class="form-control" value="{{ old('qr_expires_at') }}" required>
                </div>

                <div class="col-12">
                    <label class="form-label">Catatan</label>
                    <textarea name="catatan" class="form-control" rows="2">{{ old('catatan') }}</textarea>
                </div>
            </div>

            <div class="mt-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Buat QR Temporary</button>
                <a href="{{ route('meja.index') }}" class="btn btn-outline-secondary">Batal</a>
            </div>
        </form>
    </div>
</div>

@endsection
