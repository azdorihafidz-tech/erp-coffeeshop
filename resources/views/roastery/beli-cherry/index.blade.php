@extends('layouts.app')

@section('title', 'Beli Buah Kopi')

@section('content')
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-basket3 me-2 text-success"></i>Beli Buah Kopi</h5>
    <div class="d-flex gap-2 align-items-center">
        <x-panduan-button slug="beli-cherry" />
        @can('beli-cherry.create')
        <a href="{{ route('beli-cherry.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle me-1"></i>Buat Transaksi</a>
        @endcan
    </div>
</div>

<div class="card mb-3"><div class="card-body py-2">
    <form method="GET" class="d-flex gap-2 align-items-center">
        <select name="status" class="form-select form-select-sm" style="max-width:200px" onchange="this.form.submit()">
            <option value="">Semua status</option>
            @foreach(['draft' => 'Draft', 'disetujui' => 'Disetujui', 'diterima' => 'Diterima', 'dibatalkan' => 'Dibatalkan'] as $v => $l)
            <option value="{{ $v }}" @selected(request('status') === $v)>{{ $l }}</option>
            @endforeach
        </select>
    </form>
</div></div>

<div class="card"><div class="table-responsive">
<table class="table table-sm align-middle mb-0">
    <thead><tr><th>Kode</th><th>Tanggal</th><th>Petani</th><th>Jenis</th><th class="text-end">Qty (kg)</th><th class="text-end">Harga/kg</th><th class="text-end">Total</th><th>Status</th><th></th></tr></thead>
    <tbody>
    @forelse($purchases as $cp)
    @php $badge = ['draft' => 'secondary', 'disetujui' => 'primary', 'diterima' => 'success', 'dibatalkan' => 'danger'][$cp->status]; @endphp
    <tr>
        <td class="fw-semibold">{{ $cp->kode_transaksi }}</td>
        <td>{{ $cp->tanggal->format('d/m/Y') }}</td>
        <td>{{ $cp->petani->nama }}</td>
        <td>{{ ucfirst($cp->jenis_buah) }}</td>
        <td class="text-end">{{ number_format($cp->qty_kg, 3, ',', '.') }}</td>
        <td class="text-end">Rp {{ number_format($cp->harga_per_kg, 0, ',', '.') }}</td>
        <td class="text-end">Rp {{ number_format($cp->total_harga, 0, ',', '.') }}</td>
        <td><span class="badge bg-{{ $badge }}">{{ ucfirst($cp->status) }}</span></td>
        <td class="text-end"><a href="{{ route('beli-cherry.show', $cp) }}" class="btn btn-outline-primary btn-sm">Detail</a></td>
    </tr>
    @empty
    <tr><td colspan="9" class="text-center text-muted py-4">Belum ada transaksi beli buah kopi.</td></tr>
    @endforelse
    </tbody>
</table>
</div></div>
<div class="mt-3">{{ $purchases->links() }}</div>
@endsection
