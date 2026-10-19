@extends('layouts.app')

@section('title', 'Closing ' . $closing->kode)

@section('content')
@php $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.'); @endphp
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
        <h5 class="fw-bold mb-0">
            <i class="bi bi-journal-check me-2 text-primary"></i>Closing {{ $closing->labelJenis() }} — {{ $closing->kode }}
            @if($closing->versi > 1)<span class="badge bg-light text-dark">versi {{ $closing->versi }}</span>@endif
            {!! $closing->badgeStatus() !!}
        </h5>
        <p class="text-muted mb-0 small">{{ $closing->labelPeriode() }} · {{ $closing->namaCakupan() }}</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('closing.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
        @can('closing.export')
        <a href="{{ route('closing.pdf', $closing) }}" class="btn btn-sm btn-outline-danger"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</a>
        <a href="{{ route('closing.excel', $closing) }}" class="btn btn-sm btn-outline-success"><i class="bi bi-file-earmark-excel me-1"></i>Excel</a>
        @endcan
        <x-panduan-button slug="closing-periode" />
    </div>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show py-2">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show py-2">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if($errors->any())
<div class="alert alert-danger py-2"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

{{-- Info status --}}
<div class="card mb-3">
    <div class="card-body py-2 small">
        <div class="row g-2">
            <div class="col-md-4"><span class="text-muted">Dibuat:</span> {{ $closing->dibuatOleh?->name ?? '-' }} · {{ $closing->created_at?->translatedFormat('d M Y H:i') }}</div>
            <div class="col-md-4"><span class="text-muted">Angka dihitung:</span> {{ $closing->dihitung_pada?->translatedFormat('d M Y H:i') ?? '-' }}</div>
            @if($closing->difinalkan_pada)
            <div class="col-md-4"><span class="text-muted">Dikunci:</span> {{ $closing->difinalkanOleh?->name ?? '-' }} · {{ $closing->difinalkan_pada->translatedFormat('d M Y H:i') }}</div>
            @endif
            @if($closing->catatan)
            <div class="col-12"><span class="text-muted">Catatan:</span> {{ $closing->catatan }}</div>
            @endif
        </div>
    </div>
</div>

@if($closing->status === 'dibuka')
<div class="alert alert-secondary py-2">
    <i class="bi bi-unlock me-1"></i>Kunci dibuka oleh <strong>{{ $closing->dibukaOleh?->name ?? '-' }}</strong> pada {{ $closing->dibuka_pada?->translatedFormat('d M Y H:i') }}.
    Alasan: <em>{{ $closing->alasan_buka }}</em>. Angka di bawah adalah arsip saat closing ini dikunci.
    @can('closing.create')
    <a href="{{ route('closing.create', ['jenis' => $closing->jenis, 'tanggal_mulai' => $closing->tanggal_mulai->toDateString(), 'tanggal_selesai' => $closing->tanggal_selesai->toDateString(), 'cakupan' => $closing->cabang_id ?? 'konsolidasi']) }}" class="ms-1">Buat closing ulang →</a>
    @endcan
</div>
@elseif($closing->status === 'draft')
<div class="alert alert-warning py-2">
    <i class="bi bi-pencil me-1"></i>Masih <strong>Draft</strong> — periode belum terkunci. Saat dikunci, angka dihitung ulang dari data terbaru.
</div>
@else
<div class="alert alert-success py-2 small">
    <i class="bi bi-lock-fill me-1"></i>Terkunci. Transaksi bertanggal {{ $closing->labelPeriode() }} di {{ $closing->namaCakupan() }} tidak bisa ditambah, diubah, atau dihapus.
</div>
@endif

{{-- Aksi --}}
<div class="d-flex flex-wrap gap-2 mb-3">
    @if($closing->status === 'draft')
        @can('closing.finalize')
        <form method="POST" action="{{ route('closing.finalize', $closing) }}" onsubmit="return confirm('Kunci closing ini? Angka dihitung ulang lalu disimpan permanen, dan transaksi di periode ini tidak bisa diubah lagi.')">
            @csrf
            <button class="btn btn-success btn-sm"><i class="bi bi-lock-fill me-1"></i>Kunci (Final)</button>
        </form>
        @endcan
        @can('closing.create')
        <form method="POST" action="{{ route('closing.destroy', $closing) }}" onsubmit="return confirm('Hapus draft ini?')">
            @csrf @method('DELETE')
            <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash me-1"></i>Hapus Draft</button>
        </form>
        @endcan
    @endif
    @if($closing->status === 'final')
        @can('closing.unlock')
        <button type="button" class="btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#modalUnlock"><i class="bi bi-unlock me-1"></i>Buka Kunci</button>
        @endcan
    @endif
    @if($closing->status !== 'draft')
        @if($perbandingan)
        <a href="{{ route('closing.show', $closing) }}" class="btn btn-outline-secondary btn-sm">Sembunyikan perbandingan</a>
        @else
        <a href="{{ route('closing.show', [$closing, 'bandingkan' => 1]) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left-right me-1"></i>Bandingkan dengan data saat ini</a>
        @endif
        <x-tooltip key="closing.bandingkan" />
    @endif
</div>

@if($perbandingan)
<div class="card mb-3">
    <div class="card-header fw-semibold">Perbandingan: saat closing vs data saat ini</div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead class="table-light"><tr><th>Angka</th><th class="text-end">Saat Closing</th><th class="text-end">Data Saat Ini</th><th class="text-end">Selisih</th></tr></thead>
            <tbody>
                @foreach($perbandingan as $b)
                @php $isJumlah = $b['label'] === 'Jumlah Transaksi'; $f = fn ($n) => $isJumlah ? number_format($n, 0, ',', '.') : $rp($n); @endphp
                <tr class="{{ abs($b['selisih']) > 0.5 ? 'table-warning' : '' }}">
                    <td>{{ $b['label'] }}</td>
                    <td class="text-end">{{ $f($b['snapshot']) }}</td>
                    <td class="text-end">{{ $f($b['sekarang']) }}</td>
                    <td class="text-end fw-semibold">{{ abs($b['selisih']) > 0.5 ? $f($b['selisih']) : '—' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-footer small text-muted">Baris kuning = angka berubah setelah closing (mis. ada koreksi setelah kunci dibuka, atau setoran yang kemudian diterima).</div>
</div>
@endif

@if($versiLain->isNotEmpty())
<div class="small mb-3">
    <span class="text-muted">Versi lain periode ini:</span>
    @foreach($versiLain as $v)
    <a href="{{ route('closing.show', $v) }}" class="me-2">{{ $v->kode }} (v{{ $v->versi }}, {{ $v->status }})</a>
    @endforeach
</div>
@endif

@include('closing._snapshot', ['data' => $data])

@can('closing.unlock')
@if($closing->status === 'final')
<div class="modal fade" id="modalUnlock" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('closing.unlock', $closing) }}" class="modal-content">
            @csrf
            <div class="modal-header"><h6 class="modal-title fw-bold">Buka Kunci {{ $closing->kode }}</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <p class="small">Setelah dibuka, transaksi di periode ini bisa dikoreksi lagi. Arsip angka closing ini tetap disimpan; lakukan closing ulang setelah koreksi selesai.</p>
                <label class="form-label small fw-semibold">Alasan buka kunci <span class="text-danger">*</span></label>
                <textarea name="alasan_buka" class="form-control" rows="3" required minlength="5" maxlength="1000" placeholder="mis. Ada pengeluaran tanggal 20 yang lupa diinput"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-sm btn-warning"><i class="bi bi-unlock me-1"></i>Buka Kunci</button>
            </div>
        </form>
    </div>
</div>
@endif
@endcan
@endsection
