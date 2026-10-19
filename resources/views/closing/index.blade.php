@extends('layouts.app')

@section('title', 'Closing Periode')

@section('content')
@php $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.'); @endphp
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
    <div>
        <h5 class="fw-bold mb-0"><i class="bi bi-journal-check me-2 text-primary"></i>Closing Periode</h5>
        <p class="text-muted mb-0 small">Arsip closing harian / mingguan / bulanan / custom — angka tersimpan permanen sesuai kondisi saat closing</p>
    </div>
    <div class="d-flex gap-2 align-items-center">
        @can('closing.create')
        <a href="{{ route('closing.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>Buat Closing
        </a>
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

<form method="GET" class="mb-3 d-flex flex-wrap gap-2 align-items-end">
    @if($cabangs->count())
    <div>
        <label class="form-label form-label-sm mb-1">Cakupan</label>
        <select name="cabang_id" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
            <option value="">Semua</option>
            @if($multiCabang)
            <option value="konsolidasi" @selected(request('cabang_id') === 'konsolidasi')>Konsolidasi</option>
            @endif
            @foreach($cabangs as $c)
            <option value="{{ $c->id }}" @selected(request('cabang_id') == $c->id)>{{ $c->nama_cabang }}</option>
            @endforeach
        </select>
    </div>
    @endif
    <div>
        <label class="form-label form-label-sm mb-1">Jenis</label>
        <select name="jenis" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
            <option value="">Semua</option>
            @foreach(\App\Models\ClosingPeriode::JENIS as $k => $v)
            <option value="{{ $k }}" @selected(request('jenis') === $k)>{{ $v }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="form-label form-label-sm mb-1">Status</label>
        <select name="status" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
            <option value="">Semua</option>
            <option value="draft" @selected(request('status') === 'draft')>Draft</option>
            <option value="final" @selected(request('status') === 'final')>Final (terkunci)</option>
            <option value="dibuka" @selected(request('status') === 'dibuka')>Dibuka</option>
        </select>
    </div>
    @if(request()->hasAny(['cabang_id', 'jenis', 'status']))
    <a href="{{ route('closing.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x"></i> Reset</a>
    @endif
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Kode</th>
                    <th>Jenis</th>
                    <th>Periode</th>
                    <th>Cakupan</th>
                    <th class="text-end">Omzet</th>
                    <th class="text-end">Laba Bersih</th>
                    <th class="text-end">Kas Akhir</th>
                    <th>Status</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($closings as $c)
                <tr>
                    <td><code class="small">{{ $c->kode }}</code>@if($c->versi > 1)<span class="badge bg-light text-dark ms-1">v{{ $c->versi }}</span>@endif</td>
                    <td>{{ $c->labelJenis() }}</td>
                    <td class="text-nowrap small">{{ $c->labelPeriode() }}</td>
                    <td class="small">{{ $c->namaCakupan() }}</td>
                    <td class="text-end text-nowrap">{{ $rp($c->total_omzet) }}</td>
                    <td class="text-end text-nowrap {{ $c->laba_bersih < 0 ? 'text-danger' : '' }}">{{ $rp($c->laba_bersih) }}</td>
                    <td class="text-end text-nowrap">{{ $rp($c->total_kas_akhir) }}</td>
                    <td>{!! $c->badgeStatus() !!}</td>
                    <td class="text-center">
                        <a href="{{ route('closing.show', $c) }}" class="btn btn-sm btn-outline-primary py-0 px-2" title="Lihat"><i class="bi bi-eye"></i></a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="text-center text-muted py-5">
                        <i class="bi bi-journal-check fs-2 d-block mb-2"></i>
                        Belum ada closing. Klik "Buat Closing" untuk mulai.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($closings->hasPages())
    <div class="card-footer">{{ $closings->links() }}</div>
    @endif
</div>
@endsection
