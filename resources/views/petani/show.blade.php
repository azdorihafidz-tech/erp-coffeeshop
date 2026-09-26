@extends('layouts.app')

@section('title', 'Detail Petani')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-person-workspace me-2 text-primary"></i>{{ $petani->nama }}</h5>
    <div class="d-flex gap-2">
        @can('petani.edit')
        <a href="{{ route('petani.edit', $petani) }}" class="btn btn-outline-warning btn-sm"><i class="bi bi-pencil me-1"></i>Edit</a>
        @endcan
        <a href="{{ route('petani.index') }}" class="btn btn-outline-secondary btn-sm">Kembali</a>
    </div>
</div>

<div class="card"><div class="card-body">
    <table class="table table-sm mb-0">
        <tr><th style="width:220px">Kode Petani</th><td><code>{{ $petani->kode_petani }}</code></td></tr>
        <tr><th>Nama</th><td>{{ $petani->nama }}</td></tr>
        <tr><th>Telepon</th><td>{{ $petani->telepon ?? '-' }}</td></tr>
        <tr><th>Nama Kebun</th><td>{{ $petani->nama_kebun ?? '-' }}</td></tr>
        <tr><th>Alamat</th><td>{{ $petani->alamat ?? '-' }}</td></tr>
        <tr><th>Koordinat</th><td>
            @if($petani->koordinat_lat && $petani->koordinat_lng)
                {{ $petani->koordinat_lat }}, {{ $petani->koordinat_lng }}
            @else
                <span class="text-muted">Belum diset</span>
            @endif
        </td></tr>
        <tr><th>Catatan</th><td>{{ $petani->catatan ?? '-' }}</td></tr>
        <tr><th>Status</th><td>
            @if($petani->is_active)<span class="badge bg-success">Aktif</span>@else<span class="badge bg-secondary">Non-Aktif</span>@endif
        </td></tr>
    </table>
</div></div>

<div class="alert alert-light border mt-3 small mb-0">
    <i class="bi bi-info-circle me-1"></i>
    Riwayat pembelian buah kopi dari petani ini akan tampil di sini setelah modul <strong>Beli Buah Kopi</strong> (Roastery V2 Minggu 2-3) selesai dibangun.
</div>
@endsection
