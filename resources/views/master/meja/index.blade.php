@extends('layouts.app')

@section('title', 'Master Meja')

@section('content')

<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-grid-3x3-gap me-2 text-success"></i>Master Meja</h5>
    <div class="d-flex gap-2 align-items-center">
        <x-panduan-button slug="meja" />
        @can('master.meja.create')
        <a href="{{ route('meja.temporary.create') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-clock-history me-1"></i>QR Temporary
        </a>
        <a href="{{ route('meja.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-circle me-1"></i>Tambah Meja
        </a>
        @endcan
    </div>
</div>

<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            @if($canAllCabang)
            <div class="col-6 col-md-3">
                <select name="cabang" class="form-select form-select-sm">
                    <option value="">Semua Cabang</option>
                    @foreach($lokasiList as $cabang)
                    <option value="{{ $cabang->id }}" @selected(request('cabang') == $cabang->id)>{{ $cabang->nama_cabang }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div class="col-6 col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="">Semua Status</option>
                    <option value="aktif" @selected(request('status')=='aktif')>Aktif</option>
                    <option value="nonaktif" @selected(request('status')=='nonaktif')>Nonaktif</option>
                </select>
            </div>
            <div class="col-6 col-md-1">
                <button type="submit" class="btn btn-sm btn-outline-primary w-100"><i class="bi bi-search"></i></button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>No. Meja</th><th>Nama</th>
                        @if($canAllCabang)<th>Cabang</th>@endif
                        <th class="text-center">Kapasitas</th><th>Lokasi</th>
                        <th class="text-center">Tipe QR</th><th class="text-center">Status</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($mejas as $meja)
                    <tr>
                        <td><code>{{ $meja->nomor_meja }}</code></td>
                        <td>{{ $meja->nama_meja }}</td>
                        @if($canAllCabang)<td>{{ $meja->cabang->nama_cabang }}</td>@endif
                        <td class="text-center">{{ $meja->kapasitas ?? '-' }}</td>
                        <td><span class="badge bg-light text-dark text-capitalize">{{ $meja->lokasi }}</span></td>
                        <td class="text-center">
                            <span class="badge {{ $meja->qr_type === 'temporary' ? 'bg-warning text-dark' : 'bg-light text-dark' }}">
                                {{ $meja->qr_type === 'temporary' ? 'Temporary' : 'Permanent' }}
                            </span>
                            @if($meja->qr_type === 'temporary' && $meja->qr_expires_at)
                                <div class="small text-muted">s/d {{ $meja->qr_expires_at->translatedFormat('d M Y H:i') }}</div>
                            @endif
                        </td>
                        <td class="text-center">
                            <span class="badge {{ $meja->status === 'aktif' ? 'bg-success' : 'bg-secondary' }}">
                                {{ $meja->status === 'aktif' ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                @can('master.meja.print-qr')
                                <a href="{{ route('meja.print-qr', $meja) }}" class="btn btn-outline-success" title="Print QR Sticker">
                                    <i class="bi bi-qr-code"></i>
                                </a>
                                @endcan
                                @can('master.meja.edit')
                                <a href="{{ route('meja.edit', $meja) }}" class="btn btn-outline-warning" title="Edit"><i class="bi bi-pencil"></i></a>
                                <button type="button" class="btn btn-outline-secondary" title="Regenerate QR"
                                    onclick="if(confirm('QR lama meja {{ addslashes($meja->nama_meja) }} akan TIDAK BERFUNGSI lagi, sticker perlu dicetak ulang. Lanjutkan?')) document.getElementById('gen-{{ $meja->id }}').submit()">
                                    <i class="bi bi-arrow-repeat"></i>
                                </button>
                                <form id="gen-{{ $meja->id }}" method="POST" action="{{ route('meja.generate-qr', $meja) }}" class="d-none">
                                    @csrf
                                </form>
                                @endcan
                                @can('master.meja.delete')
                                <button type="button" class="btn btn-outline-danger" title="Hapus"
                                    onclick="if(confirm('Hapus {{ addslashes($meja->nama_meja) }}?')) document.getElementById('del-{{ $meja->id }}').submit()">
                                    <i class="bi bi-trash"></i>
                                </button>
                                <form id="del-{{ $meja->id }}" method="POST" action="{{ route('meja.destroy', $meja) }}" class="d-none">
                                    @csrf @method('DELETE')
                                </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="{{ $canAllCabang ? 8 : 7 }}" class="text-center text-muted py-4">Belum ada meja</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-3">{{ $mejas->links() }}</div>

@endsection
