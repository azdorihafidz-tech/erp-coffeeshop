{{-- Bug 21 (2026-11-17) — lokasi tempat transaksi Roastery dicatat (Cabang::lokasiRoastery()). --}}
@php $lokasiRoastery = \App\Models\Cabang::lokasiRoastery(); @endphp
@if($lokasiRoastery)
<div class="small text-muted mb-2">
    <i class="bi bi-geo-alt me-1"></i>Lokasi Roastery: <strong>{{ $lokasiRoastery->nama_cabang }}</strong> ({{ $lokasiRoastery->kode_cabang }})
</div>
@else
<div class="alert alert-warning py-2 small">
    <i class="bi bi-exclamation-triangle me-1"></i>Belum ada lokasi <strong>Roastery</strong> aktif. Buat cabang bertipe Roastery di
    <strong>Pengaturan → Cabang &amp; Gudang</strong> supaya transaksi Roastery bisa disimpan.
</div>
@endif
