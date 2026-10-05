{{--
    Sprint Analytics Laporan Penjualan (2026-10-05) — 5 kartu metrik untuk
    laporan. BEDA dari partial dashboard: tidak ada dropdown filter (filter
    ambil dari form Dari-Sampai+Cabang di atas laporan), styling compact
    (karena tabel detail panjang di bawahnya).

    Butuh variabel: $salesAnalytics (array dari SalesAnalyticsService::getAllMetricsByDateRange).
--}}
@php
    $sa = $salesAnalytics ?? null;
    $tanggalFmt = fn ($t) => $t ? $t->translatedFormat('d M Y') : '-';
    $rupiah = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
@endphp

@if($sa)
<div class="d-flex align-items-center mb-2">
    <h6 class="fw-bold mb-0 text-muted" style="font-size:0.78rem;letter-spacing:.03em;text-transform:uppercase">
        Analytics Penjualan ({{ $sa['periode_label'] }})
    </h6>
    <x-tooltip key="laporan_penjualan.analytics_section" placement="right" />
</div>
<div class="row g-2 mb-3">
    <div class="col-6 col-md-4 col-xl">
        <div class="stat-card p-2">
            <div class="fw-bold text-primary" style="font-size:0.95rem">
                {{ $sa['avg_tx_value'] !== null ? $rupiah($sa['avg_tx_value']) : '—' }}
            </div>
            <div class="text-muted" style="font-size:0.72rem">Rata-rata Nilai / Transaksi</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl">
        <div class="stat-card p-2">
            <div class="fw-bold text-info" style="font-size:0.95rem">
                {{ $sa['avg_daily_tx'] !== null ? number_format($sa['avg_daily_tx'], 1, ',', '.') . ' tx/hari' : '—' }}
            </div>
            <div class="text-muted" style="font-size:0.72rem">Rata-rata Jumlah Transaksi</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl">
        <div class="stat-card p-2">
            @if($sa['top_value_day'])
                <div class="fw-bold text-success" style="font-size:0.9rem">
                    {{ $rupiah($sa['top_value_day']['omzet']) }}
                </div>
                <div class="text-muted" style="font-size:0.72rem">
                    Hari Omzet Tertinggi: <span class="text-primary">{{ $tanggalFmt($sa['top_value_day']['tanggal']) }}</span> · {{ $sa['top_value_day']['jumlah'] }} tx
                </div>
            @else
                <div class="fw-bold text-muted" style="font-size:0.9rem">—</div>
                <div class="text-muted" style="font-size:0.72rem">Hari Omzet Tertinggi</div>
            @endif
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl">
        <div class="stat-card p-2">
            @if($sa['top_qty_day'])
                <div class="fw-bold text-warning" style="font-size:0.9rem">
                    {{ $sa['top_qty_day']['jumlah'] }} tx
                </div>
                <div class="text-muted" style="font-size:0.72rem">
                    Hari Tx Terbanyak: <span class="text-primary">{{ $tanggalFmt($sa['top_qty_day']['tanggal']) }}</span> · {{ $rupiah($sa['top_qty_day']['omzet']) }}
                </div>
            @else
                <div class="fw-bold text-muted" style="font-size:0.9rem">—</div>
                <div class="text-muted" style="font-size:0.72rem">Hari Tx Terbanyak</div>
            @endif
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl">
        <div class="stat-card p-2">
            <div class="fw-bold text-secondary" style="font-size:0.95rem">
                {{ $sa['avg_daily_revenue'] !== null ? $rupiah($sa['avg_daily_revenue']) : '—' }}
            </div>
            <div class="text-muted" style="font-size:0.72rem">Rata-rata Omzet Harian</div>
        </div>
    </div>
</div>
@endif
