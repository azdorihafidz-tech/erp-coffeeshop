@extends('layouts.app')

@section('title', 'Roasting Profile')

@section('content')
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-fire me-2 text-success"></i>Roasting Profile</h5>
    <div class="d-flex gap-2 align-items-center">
        <x-panduan-button slug="roastery-profile" />
        @can('roastery.profile.create')
        <a href="{{ route('roastery.profile.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle me-1"></i>Tambah Profile</a>
        @endcan
    </div>
</div>
<div class="card"><div class="table-responsive">
<table class="table table-sm align-middle mb-0">
    <thead><tr><th>Nama</th><th>Level</th><th class="text-end">Susut %</th><th class="text-end">Suhu</th><th class="text-end">Waktu</th><th>Batch</th><th>Status</th><th></th></tr></thead>
    <tbody>
    @forelse($profiles as $p)
    <tr>
        <td class="fw-semibold">{{ $p->nama }}</td>
        <td>{{ ucfirst($p->level_target) }}</td>
        <td class="text-end">{{ (float) $p->avg_susut_percent }}%</td>
        <td class="text-end">{{ $p->suhu_target_celsius ? $p->suhu_target_celsius.'°C' : '-' }}</td>
        <td class="text-end">{{ $p->waktu_target_menit ? $p->waktu_target_menit.' mnt' : '-' }}</td>
        <td>{{ $p->batches_count }}</td>
        <td><span class="badge bg-{{ $p->is_active ? 'success' : 'secondary' }}">{{ $p->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
        <td class="text-end text-nowrap">
            @can('roastery.profile.edit')<a href="{{ route('roastery.profile.edit', $p) }}" class="btn btn-outline-primary btn-sm">Edit</a>@endcan
            @can('roastery.profile.delete')
            <form method="POST" action="{{ route('roastery.profile.destroy', $p) }}" class="d-inline" onsubmit="return confirm('Hapus profile ini?')">
                @csrf @method('DELETE')
                <button class="btn btn-outline-danger btn-sm">Hapus</button>
            </form>
            @endcan
        </td>
    </tr>
    @empty
    <tr><td colspan="8" class="text-center text-muted py-4">Belum ada profile.</td></tr>
    @endforelse
    </tbody>
</table>
</div></div>
@endsection
