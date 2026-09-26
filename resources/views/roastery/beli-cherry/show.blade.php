@extends('layouts.app')

@section('title', $cp->kode_transaksi)

@section('content')
@php $badge = ['draft' => 'secondary', 'disetujui' => 'primary', 'diterima' => 'success', 'dibatalkan' => 'danger'][$cp->status]; @endphp
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-basket3 me-2 text-success"></i>{{ $cp->kode_transaksi }}
        <span class="badge bg-{{ $badge }} ms-2">{{ ucfirst($cp->status) }}</span></h5>
    <div class="d-flex gap-2 align-items-center">
        <x-panduan-button slug="beli-cherry" />
        <a href="{{ route('beli-cherry.index') }}" class="btn btn-outline-secondary btn-sm">Kembali</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-7">
        <div class="card"><div class="card-body">
            <table class="table table-sm mb-0">
                <tr><th style="width:40%">Tanggal</th><td>{{ $cp->tanggal->format('d/m/Y') }}</td></tr>
                <tr><th>Petani</th><td>{{ $cp->petani->nama }} ({{ $cp->petani->kode_petani }})@if($cp->petani->nama_kebun) — {{ $cp->petani->nama_kebun }}@endif</td></tr>
                <tr><th>Jenis Buah</th><td>{{ ucfirst($cp->jenis_buah) }} — {{ $cp->cherryItem->nama_item }}</td></tr>
                <tr><th>Qty Dipesan</th><td>{{ number_format($cp->qty_kg, 3, ',', '.') }} kg</td></tr>
                <tr><th>Harga per kg</th><td>Rp {{ number_format($cp->harga_per_kg, 0, ',', '.') }}</td></tr>
                <tr><th>Total Estimasi</th><td>Rp {{ number_format($cp->total_harga, 0, ',', '.') }}</td></tr>
                <tr><th>Grade Kualitas</th><td>{{ $cp->kualitas_grade ? 'Grade '.$cp->kualitas_grade : '-' }}</td></tr>
                @if($cp->status === 'diterima')
                <tr><th>Qty Terima <x-tooltip key="beli-cherry.qty_terima_kg" /></th><td class="fw-semibold">{{ number_format($cp->qty_terima_kg, 3, ',', '.') }} kg</td></tr>
                <tr><th>Diterima oleh</th><td>{{ $cp->diterimaOleh?->name }} — {{ $cp->diterima_at?->format('d/m/Y H:i') }}</td></tr>
                @endif
                @if($cp->disetujui_by)
                <tr><th>Disetujui oleh</th><td>{{ $cp->disetujuiOleh?->name }} — {{ $cp->disetujui_at?->format('d/m/Y H:i') }}</td></tr>
                @endif
                <tr><th>Dibuat oleh</th><td>{{ $cp->user->name }}</td></tr>
                @if($cp->catatan)<tr><th>Catatan</th><td>{{ $cp->catatan }}</td></tr>@endif
            </table>
        </div></div>
    </div>

    <div class="col-12 col-lg-5">
        <div class="card"><div class="card-body d-flex flex-column gap-2">
            @if($cp->status === 'draft')
                @can('beli-cherry.edit')
                <form method="POST" action="{{ route('beli-cherry.setujui', $cp) }}" onsubmit="return confirm('Setujui transaksi ini?')">
                    @csrf <button class="btn btn-primary w-100">Setujui</button>
                </form>
                @endcan
                @can('beli-cherry.batalkan')
                <form method="POST" action="{{ route('beli-cherry.batalkan', $cp) }}" onsubmit="return confirm('Batalkan transaksi ini?')">
                    @csrf <button class="btn btn-outline-danger w-100">Batalkan</button>
                </form>
                @endcan
            @elseif($cp->status === 'disetujui')
                @can('beli-cherry.terima')
                <form method="POST" action="{{ route('beli-cherry.terima', $cp) }}">
                    @csrf
                    <label class="form-label small fw-semibold">Qty Terima (kg) * <x-tooltip key="beli-cherry.qty_terima_kg" /></label>
                    <input type="number" step="0.001" min="0.001" name="qty_terima_kg" class="form-control mb-2" value="{{ old('qty_terima_kg', $cp->qty_kg) }}" required>
                    <button class="btn btn-success w-100">Terima — Stok Masuk RST001</button>
                </form>
                @endcan
                @can('beli-cherry.batalkan')
                <form method="POST" action="{{ route('beli-cherry.batalkan', $cp) }}" onsubmit="return confirm('Batalkan transaksi ini?')">
                    @csrf <button class="btn btn-outline-danger w-100">Batalkan</button>
                </form>
                @endcan
            @elseif($cp->status === 'diterima')
                <div class="text-success small"><i class="bi bi-check-circle-fill me-1"></i>Stok sudah masuk ke RST001, tidak bisa dibatalkan lagi.</div>
            @else
                <div class="text-muted small">Transaksi ini sudah dibatalkan.</div>
            @endif
        </div></div>
    </div>
</div>
@endsection
