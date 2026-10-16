@extends('layouts.app')

@section('title', 'Go-Live Reset Production')

@section('content')
<div class="container" style="max-width: 760px;">
    <div class="card border-danger">
        <div class="card-header bg-danger text-white">
            <h4 class="mb-0 fw-bold"><i class="bi bi-exclamation-triangle me-2"></i>Go-Live Reset — Hapus Total Data</h4>
        </div>
        <div class="card-body">
            @if(session('error'))
                <div class="alert alert-danger"><i class="bi bi-x-circle me-1"></i>{{ session('error') }}</div>
            @endif

            <div class="alert alert-danger">
                <strong>PERINGATAN: Tindakan ini TIDAK BISA DI-UNDO!</strong><br>
                Semua data di database production akan DIHAPUS dan tabel di-create ulang dari nol.
            </div>

            <h5 class="mt-4">Yang akan dilakukan:</h5>
            <ul class="small">
                <li><strong>DROP semua tabel</strong> di database production</li>
                <li><strong>Re-create skema</strong> dari migration (fresh)</li>
                <li><strong>Seed master data saja</strong>: cabang, user, role, permission, item master, resep, panduan, tooltip, COA, dll</li>
                <li><strong>TIDAK ikut</strong>: supplier dummy, karyawan dummy, stok testing, meja testing, transaksi contoh</li>
                <li><strong>Kas</strong> dibuat saldo awal Rp0 (isi via menu Keuangan nanti)</li>
            </ul>

            <div class="alert alert-warning small">
                <strong><i class="bi bi-shield-exclamation me-1"></i>Pastikan sudah backup database!</strong><br>
                Download backup .sql via cPanel → phpMyAdmin → Export sebelum lanjut.
            </div>

            <h5 class="mt-4">Setelah reset, akun login default:</h5>
            <ul class="small">
                <li><code>admin@kopidrip.com</code> / <code>password</code> (Owner)</li>
                <li><code>manajer.pusat@kopidrip.com</code> / <code>password</code></li>
                <li><code>manajer.o1</code> s/d <code>o5</code> (per outlet)</li>
                <li><code>kasir.o1</code>, <code>barista.o1</code> (dst)</li>
            </ul>
            <p class="small text-danger"><strong>⚠️ WAJIB ganti password semua akun setelah login pertama.</strong></p>

            <hr>
            <form method="POST" action="{{ route('go-live-reset.execute') }}">
                @csrf
                <label class="form-label fw-semibold">
                    Untuk konfirmasi, ketik PERSIS phrase berikut:
                </label>
                <p class="mb-2"><code class="fs-6 bg-light p-2 d-inline-block">{{ $confirmPhrase }}</code></p>
                <input type="text" name="confirm_phrase" class="form-control form-control-lg mb-3"
                    placeholder="Ketik phrase di atas persis sama..." autocomplete="off" required>
                <div class="d-grid">
                    <button type="submit" class="btn btn-danger btn-lg fw-bold"
                        onclick="return confirm('Yakin mau HAPUS TOTAL data production? Backup sudah ada?');">
                        <i class="bi bi-fire me-1"></i>EKSEKUSI RESET PRODUCTION
                    </button>
                </div>
            </form>

            <div class="mt-4 small text-muted">
                <strong>Keamanan:</strong> halaman ini hanya bisa diakses user dengan role <code>owner</code>.
                Login sebagai akun lain → otomatis 403.
            </div>
        </div>
    </div>
</div>
@endsection
