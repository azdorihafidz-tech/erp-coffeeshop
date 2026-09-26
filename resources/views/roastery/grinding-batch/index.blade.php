@extends('layouts.app')

@section('title', 'Grinding Batch')

@section('content')
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-gear-wide-connected me-2 text-success"></i>Grinding Batch</h5>
    <div class="d-flex gap-2 align-items-center">
        <x-panduan-button slug="grinding-batch" />
        @can('grinding-batch.create')
        <a href="{{ route('grinding-batch.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle me-1"></i>Batch Baru</a>
        @endcan
    </div>
</div>

<div class="card"><div class="table-responsive">
<table class="table table-sm align-middle mb-0">
    <thead><tr><th>Kode</th><th>Tanggal</th><th>Roasted Bean</th><th>Grind Size</th><th class="text-end">Qty In (kg)</th><th class="text-end">Qty Out (kg)</th><th>Status</th><th></th></tr></thead>
    <tbody>
    @forelse($batches as $b)
    @php $badge = ['draft' => 'secondary', 'selesai' => 'success', 'dibatalkan' => 'danger'][$b->status]; @endphp
    <tr>
        <td class="fw-semibold">{{ $b->kode_batch }}</td>
        <td>{{ $b->tanggal->format('d/m/Y') }}</td>
        <td>{{ $b->roastedItem->nama_item }}</td>
        <td>{{ $b->grind_size->label() }}</td>
        <td class="text-end">{{ number_format($b->roasted_qty_kg_in, 3, ',', '.') }}</td>
        <td class="text-end">{{ $b->ground_qty_kg_out ? number_format($b->ground_qty_kg_out, 3, ',', '.') : '-' }}</td>
        <td><span class="badge bg-{{ $badge }}">{{ ucfirst($b->status) }}</span></td>
        <td class="text-end"><a href="{{ route('grinding-batch.show', $b) }}" class="btn btn-outline-primary btn-sm">Detail</a></td>
    </tr>
    @empty
    <tr><td colspan="8" class="text-center text-muted py-4">Belum ada grinding batch.</td></tr>
    @endforelse
    </tbody>
</table>
</div></div>
<div class="mt-3">{{ $batches->links() }}</div>
@endsection
