@extends('layouts.app')

@section('title', 'Update Database')

@section('content')
<div class="container" style="max-width: 760px;">
    <div class="card border-success">
        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold"><i class="bi bi-database-up me-2"></i>Update Database (aman, data tidak dihapus)</h5>
            <x-panduan-button slug="update-database" variant="light" />
        </div>
        <div class="card-body">
            <div class="alert alert-info small">
                Jalankan halaman ini <strong>setiap selesai upload kode baru</strong> ke server.
                Data transaksi, stok, kas, dan pengaturan hak akses Anda <strong>tidak dihapus</strong>.
                Ini BUKAN halaman <code>/go-live-reset</code> (yang mengosongkan database).
            </div>

            <h6 class="fw-semibold">Migration yang akan dijalankan</h6>
            @if(empty($pending))
                <p class="small text-success"><i class="bi bi-check-circle me-1"></i>Tidak ada migration baru — database sudah sesuai kode.</p>
            @else
                <ul class="small">
                    @foreach($pending as $m)<li><code>{{ $m }}</code></li>@endforeach
                </ul>
            @endif

            <h6 class="fw-semibold mt-3">Konten yang diperbarui</h6>
            <p class="small mb-2">{{ implode(', ', $seeders) }} (permission, panduan Cara Pakai, tooltip).</p>

            <h6 class="fw-semibold mt-3">Hak akses fitur baru (ditambah kalau belum ada)</h6>
            <table class="table table-sm small">
                <thead class="table-light"><tr><th>Permission</th><th>Role</th></tr></thead>
                <tbody>
                    @foreach($permissionBaru as $perm => $roles)
                    <tr><td><code>{{ $perm }}</code></td><td>{{ implode(', ', $roles) }}</td></tr>
                    @endforeach
                </tbody>
            </table>

            @isset($logs)
            <h6 class="fw-semibold mt-3">Hasil</h6>
            <div class="border rounded p-2 small" style="font-family:monospace;background:#f8fafc;white-space:pre-wrap">@foreach($logs as $l)<div class="{{ $l['type'] === 'err' ? 'text-danger' : ($l['type'] === 'ok' ? 'text-success' : '') }}">[{{ $l['ts'] }}] {{ $l['msg'] }}</div>@endforeach</div>
            @endisset

            <form method="POST" action="{{ route('update-database.execute') }}" class="mt-3"
                  onsubmit="this.querySelector('button').disabled = true; this.querySelector('button').innerText = 'Menjalankan... (jangan tutup halaman)';">
                @csrf
                <button type="submit" class="btn btn-success"><i class="bi bi-play-fill me-1"></i>Jalankan Update Database</button>
            </form>
            <p class="small text-muted mt-2 mb-0">Disarankan backup database dulu (cPanel → phpMyAdmin → Export) sebelum menjalankan.</p>
        </div>
    </div>
</div>
@endsection
