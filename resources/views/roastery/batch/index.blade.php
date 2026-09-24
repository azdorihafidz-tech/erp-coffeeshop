@extends('layouts.app')

@section('title', 'Batch Roasting')

@section('content')
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-cup-hot me-2 text-success"></i>Batch Roasting</h5>
    <div class="d-flex gap-2 align-items-center">
        <x-panduan-button slug="roastery-batch" />
        @can('roastery.batch.create')
        <a href="{{ route('roastery.batch.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle me-1"></i>Batch Baru</a>
        @endcan
    </div>
</div>

<div class="card mb-3"><div class="card-body py-2">
    <form method="GET" class="d-flex gap-2 align-items-center">
        <select name="status" class="form-select form-select-sm" style="max-width:180px" onchange="this.form.submit()">
            <option value="">Semua status</option>
            @foreach(['draft' => 'Draft', 'completed' => 'Selesai', 'cancelled' => 'Dibatalkan'] as $v => $l)
            <option value="{{ $v }}" @selected(request('status') === $v)>{{ $l }}</option>
            @endforeach
        </select>
    </form>
</div></div>

<div class="card"><div class="table-responsive">
<table class="table table-sm align-middle mb-0">
    <thead><tr><th>No. Batch</th><th>Tanggal</th><th>Green Bean</th><th>Profile</th><th class="text-end">Green (kg)</th><th class="text-end">Roasted (kg)</th><th class="text-end">Yield</th><th class="text-end">Cost/kg</th><th>Status</th><th></th></tr></thead>
    <tbody>
    @forelse($batches as $b)
    @php $badge = ['draft' => 'secondary', 'completed' => 'success', 'cancelled' => 'danger'][$b->status]; @endphp
    <tr>
        <td class="fw-semibold">{{ $b->nomor_batch }}</td>
        <td>{{ $b->tanggal->format('d/m/Y') }}</td>
        <td>{{ $b->greenBean->nama_item }}</td>
        <td>{{ $b->profile->nama }}</td>
        <td class="text-end">{{ number_format($b->green_qty_kg, 3, ',', '.') }}</td>
        <td class="text-end">{{ number_format($b->roasted_qty_kg, 3, ',', '.') }}</td>
        <td class="text-end">{{ number_format($b->yield_rate_percent, 2, ',', '.') }}%</td>
        <td class="text-end">{{ $b->status === 'completed' ? 'Rp '.number_format($b->cost_per_kg_roasted, 0, ',', '.') : '-' }}</td>
        <td><span class="badge bg-{{ $badge }}">{{ ['draft' => 'Draft', 'completed' => 'Selesai', 'cancelled' => 'Dibatalkan'][$b->status] }}</span></td>
        <td class="text-end"><a href="{{ route('roastery.batch.show', $b) }}" class="btn btn-outline-primary btn-sm">Detail</a></td>
    </tr>
    @empty
    <tr><td colspan="10" class="text-center text-muted py-4">Belum ada batch.</td></tr>
    @endforelse
    </tbody>
</table>
</div></div>
<div class="mt-3">{{ $batches->links() }}</div>
@endsection
