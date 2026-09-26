@extends('layouts.app')

@section('title', 'Processing Batch')

@section('content')
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-droplet-half me-2 text-success"></i>Processing Batch</h5>
    <div class="d-flex gap-2 align-items-center">
        <x-panduan-button slug="processing-batch" />
        @can('processing-batch.create')
        <a href="{{ route('processing-batch.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle me-1"></i>Batch Baru</a>
        @endcan
    </div>
</div>

<div class="card mb-3"><div class="card-body py-2">
    <form method="GET" class="d-flex gap-2 align-items-center">
        <select name="status" class="form-select form-select-sm" style="max-width:200px" onchange="this.form.submit()">
            <option value="">Semua status</option>
            @foreach(['draft' => 'Draft', 'fermentasi' => 'Fermentasi', 'drying' => 'Drying', 'hulling' => 'Hulling', 'sortir' => 'Sortir', 'selesai' => 'Selesai', 'dibatalkan' => 'Dibatalkan'] as $v => $l)
            <option value="{{ $v }}" @selected(request('status') === $v)>{{ $l }}</option>
            @endforeach
        </select>
    </form>
</div></div>

<div class="card"><div class="table-responsive">
<table class="table table-sm align-middle mb-0">
    <thead><tr><th>Kode</th><th>Tanggal Mulai</th><th>Cherry</th><th>Method</th><th class="text-end">Qty (kg)</th><th class="text-end">Yield</th><th>Status</th><th></th></tr></thead>
    <tbody>
    @forelse($batches as $b)
    @php $badge = ['draft' => 'secondary', 'fermentasi' => 'warning', 'drying' => 'info', 'hulling' => 'info', 'sortir' => 'primary', 'selesai' => 'success', 'dibatalkan' => 'danger'][$b->status]; @endphp
    <tr>
        <td class="fw-semibold">{{ $b->kode_batch }}</td>
        <td>{{ $b->tanggal_mulai->format('d/m/Y') }}</td>
        <td>{{ $b->cherryItem->nama_item }}</td>
        <td>{{ $b->processing_method->label() }}</td>
        <td class="text-end">{{ number_format($b->cherry_qty_kg, 3, ',', '.') }}</td>
        <td class="text-end">{{ $b->yield_percent ? number_format($b->yield_percent, 2, ',', '.').'%' : '-' }}</td>
        <td><span class="badge bg-{{ $badge }} {{ $b->status === 'fermentasi' ? 'text-dark' : '' }}">{{ ucfirst($b->status) }}</span></td>
        <td class="text-end"><a href="{{ route('processing-batch.show', $b) }}" class="btn btn-outline-primary btn-sm">Detail</a></td>
    </tr>
    @empty
    <tr><td colspan="8" class="text-center text-muted py-4">Belum ada processing batch.</td></tr>
    @endforelse
    </tbody>
</table>
</div></div>
<div class="mt-3">{{ $batches->links() }}</div>
@endsection
