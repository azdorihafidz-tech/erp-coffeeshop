@extends('layouts.app')

@section('title', 'Master Petani')

@section('content')
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-person-workspace me-2 text-primary"></i>Master Petani</h5>
    <div class="d-flex gap-2 align-items-center">
        @can('petani.create')
        <a href="{{ route('petani.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-circle me-1"></i>Tambah Petani
        </a>
        @endcan
        <x-panduan-button slug="petani" />
    </div>
</div>

<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-12 col-sm-6 col-md-5">
                <input type="text" name="search" class="form-control form-control-sm"
                    placeholder="Cari nama / kode / kebun..." value="{{ request('search') }}">
            </div>
            <div class="col-12 col-sm-4 col-md-3">
                <select name="status" class="form-select form-select-sm">
                    <option value="">Semua Status</option>
                    <option value="aktif" @selected(request('status')=='aktif')>Aktif</option>
                    <option value="nonaktif" @selected(request('status')=='nonaktif')>Non-Aktif</option>
                </select>
            </div>
            <div class="col-12 col-sm-2 col-md-2">
                <button type="submit" class="btn btn-sm btn-outline-primary w-100">
                    <i class="bi bi-search"></i> Cari
                </button>
            </div>
            @if(request()->hasAny(['search','status']))
            <div class="col-12 col-sm-2 col-md-2">
                <a href="{{ route('petani.index') }}" class="btn btn-sm btn-outline-secondary w-100">Reset</a>
            </div>
            @endif
        </form>
    </div>
</div>

{{-- Desktop Table --}}
<div class="card d-none d-md-block">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Nama Petani</th>
                        <th>Kebun</th>
                        <th>Telepon</th>
                        <th class="text-center">Status</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($petanis as $p)
                    <tr>
                        <td><code class="text-primary">{{ $p->kode_petani }}</code></td>
                        <td class="fw-semibold">{{ $p->nama }}</td>
                        <td>{{ $p->nama_kebun ?? '-' }}</td>
                        <td>{{ $p->telepon ?? '-' }}</td>
                        <td class="text-center">
                            @if($p->is_active)
                                <span class="badge bg-success">Aktif</span>
                            @else
                                <span class="badge bg-secondary">Non-Aktif</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('petani.show', $p) }}" class="btn btn-outline-info" title="Lihat">
                                    <i class="bi bi-eye"></i>
                                </a>
                                @can('petani.edit')
                                <a href="{{ route('petani.edit', $p) }}" class="btn btn-outline-warning" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="POST" action="{{ route('petani.toggle-aktif', $p) }}" class="d-inline">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="btn btn-outline-{{ $p->is_active ? 'secondary' : 'success' }}" title="{{ $p->is_active ? 'Nonaktifkan' : 'Aktifkan' }}">
                                        <i class="bi bi-{{ $p->is_active ? 'toggle-on' : 'toggle-off' }}"></i>
                                    </button>
                                </form>
                                @endcan
                                @can('petani.delete')
                                <form method="POST" action="{{ route('petani.destroy', $p) }}" class="d-inline" onsubmit="return confirm('Hapus petani {{ addslashes($p->nama) }}?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger" title="Hapus">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            <i class="bi bi-inbox fs-3 d-block mb-2"></i>Tidak ada data petani
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Mobile Card View --}}
<div class="d-md-none">
    @forelse($petanis as $p)
    <div class="card mb-2">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <div class="fw-semibold">{{ $p->nama }}</div>
                    <code class="text-primary small">{{ $p->kode_petani }}</code>
                </div>
                @if($p->is_active)
                    <span class="badge bg-success">Aktif</span>
                @else
                    <span class="badge bg-secondary">Non-Aktif</span>
                @endif
            </div>
            <div class="text-muted small mb-2">
                @if($p->nama_kebun)<div><i class="bi bi-tree me-1"></i>{{ $p->nama_kebun }}</div>@endif
                @if($p->telepon)<div><i class="bi bi-telephone me-1"></i>{{ $p->telepon }}</div>@endif
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('petani.show', $p) }}" class="btn btn-sm btn-outline-info flex-fill">
                    <i class="bi bi-eye"></i> Lihat
                </a>
                @can('petani.edit')
                <a href="{{ route('petani.edit', $p) }}" class="btn btn-sm btn-outline-warning flex-fill">
                    <i class="bi bi-pencil"></i> Edit
                </a>
                @endcan
                @can('petani.delete')
                <form method="POST" action="{{ route('petani.destroy', $p) }}" onsubmit="return confirm('Hapus petani {{ addslashes($p->nama) }}?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-outline-danger">
                        <i class="bi bi-trash"></i>
                    </button>
                </form>
                @endcan
            </div>
        </div>
    </div>
    @empty
    <div class="text-center text-muted py-5">
        <i class="bi bi-inbox fs-3 d-block mb-2"></i>Tidak ada data petani
    </div>
    @endforelse
</div>

<div class="mt-3">
    {{ $petanis->links() }}
</div>
@endsection
