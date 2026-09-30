{{--
    Sprint Analytics Dashboard (2026-10-02) — 5 metrik penjualan + dropdown
    filter periode global. Reusable oleh dashboard.pusat & dashboard.cabang.

    Butuh 2 variabel dari controller:
      $salesAnalytics        (array getAllMetrics)
      $salesAnalyticsPeriode (string periode aktif)

    $scope = 'pusat' | 'cabang' — dipakai supaya form GET reload ke route yg
    benar tanpa kehilangan query param lain (analytics ini murni filter
    periode, jadi form pakai action ke route dashboard saat ini).
--}}
@php
    /** @var array $salesAnalytics */
    $sa = $salesAnalytics ?? null;
    $periodeAktif = $salesAnalyticsPeriode ?? 'bulan';
    $formAction = ($scope ?? 'pusat') === 'cabang' ? route('dashboard.cabang') : route('dashboard.pusat');
    $tanggalFmt = fn ($t) => $t ? $t->translatedFormat('d M Y') : '-';
    $rupiah = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
@endphp

@if($sa)
<div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
    <h6 class="fw-bold mb-0 text-muted" style="font-size:0.8rem;letter-spacing:.03em;text-transform:uppercase">
        Analytics Penjualan
    </h6>
    <form method="GET" action="{{ $formAction }}" class="d-inline-flex align-items-center gap-2 mb-0">
        <label for="filterPeriodeAnalytics" class="small text-muted mb-0">
            Rentang <x-tooltip key="dashboard_analytics.periode" placement="top" />
        </label>
        <select name="periode" id="filterPeriodeAnalytics" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
            @foreach(\App\Services\SalesAnalyticsService::PERIODE_LABELS as $val => $label)
                <option value="{{ $val }}" @selected($periodeAktif === $val)>{{ $label }}</option>
            @endforeach
        </select>
    </form>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-4 col-xl">
        <div class="stat-card">
            <div class="stat-icon bg-primary bg-opacity-10 mb-2"><i class="bi bi-cash-coin text-primary"></i></div>
            <div class="fw-bold" style="font-size:1.15rem;color:#1e293b">
                {{ $sa['avg_tx_value'] !== null ? $rupiah($sa['avg_tx_value']) : '—' }}
            </div>
            <div class="text-muted" style="font-size:0.78rem">
                Rata-rata Nilai per Transaksi
                <x-tooltip key="dashboard_analytics.avg_tx_value" placement="top" />
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-4 col-xl">
        <div class="stat-card">
            <div class="stat-icon bg-info bg-opacity-10 mb-2"><i class="bi bi-receipt text-info"></i></div>
            <div class="fw-bold" style="font-size:1.15rem;color:#1e293b">
                {{ $sa['avg_daily_tx'] !== null ? number_format($sa['avg_daily_tx'], 1, ',', '.') . ' tx/hari' : '—' }}
            </div>
            <div class="text-muted" style="font-size:0.78rem">
                Rata-rata Jumlah Transaksi
                <x-tooltip key="dashboard_analytics.avg_daily_tx" placement="top" />
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-4 col-xl">
        <div class="stat-card">
            <div class="stat-icon bg-success bg-opacity-10 mb-2"><i class="bi bi-trophy text-success"></i></div>
            @if($sa['top_value_day'])
                <div class="fw-bold" style="font-size:1.05rem;color:#1e293b">
                    {{ $rupiah($sa['top_value_day']['omzet']) }}
                </div>
                <div class="text-muted" style="font-size:0.78rem">
                    Hari Omzet Tertinggi<br>
                    <span class="text-primary">{{ $tanggalFmt($sa['top_value_day']['tanggal']) }}</span>
                    · {{ $sa['top_value_day']['jumlah'] }} tx
                </div>
            @else
                <div class="fw-bold" style="font-size:1.05rem;color:#94a3b8">—</div>
                <div class="text-muted" style="font-size:0.78rem">Hari Omzet Tertinggi</div>
            @endif
        </div>
    </div>
    <div class="col-6 col-lg-4 col-xl">
        <div class="stat-card">
            <div class="stat-icon bg-warning bg-opacity-10 mb-2"><i class="bi bi-graph-up-arrow text-warning"></i></div>
            @if($sa['top_qty_day'])
                <div class="fw-bold" style="font-size:1.05rem;color:#1e293b">
                    {{ $sa['top_qty_day']['jumlah'] }} tx
                </div>
                <div class="text-muted" style="font-size:0.78rem">
                    Hari Transaksi Terbanyak<br>
                    <span class="text-primary">{{ $tanggalFmt($sa['top_qty_day']['tanggal']) }}</span>
                    · {{ $rupiah($sa['top_qty_day']['omzet']) }}
                </div>
            @else
                <div class="fw-bold" style="font-size:1.05rem;color:#94a3b8">—</div>
                <div class="text-muted" style="font-size:0.78rem">Hari Transaksi Terbanyak</div>
            @endif
        </div>
    </div>
    <div class="col-6 col-lg-4 col-xl">
        <div class="stat-card">
            <div class="stat-icon bg-secondary bg-opacity-10 mb-2"><i class="bi bi-calendar3 text-secondary"></i></div>
            <div class="fw-bold" style="font-size:1.15rem;color:#1e293b">
                {{ $sa['avg_daily_revenue'] !== null ? $rupiah($sa['avg_daily_revenue']) : '—' }}
            </div>
            <div class="text-muted" style="font-size:0.78rem">
                Rata-rata Omzet Harian
                <x-tooltip key="dashboard_analytics.avg_daily_revenue" placement="top" />
            </div>
        </div>
    </div>
</div>
@endif
