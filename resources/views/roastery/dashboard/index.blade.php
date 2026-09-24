@extends('layouts.app')

@section('title', 'Dashboard Roastery')

@section('content')
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-speedometer2 me-2 text-success"></i>Dashboard Roastery</h5>
    <div class="d-flex gap-2 align-items-center">
        <x-panduan-button slug="roastery-dashboard" />
        <form method="GET"><input type="month" name="bulan" value="{{ $bulan->format('Y-m') }}" class="form-control form-control-sm" onchange="this.form.submit()"></form>
    </div>
</div>

<div class="row g-3 mb-3">
    @foreach([
        ['Batch selesai', $ringkasan['jumlah_batch'], ''],
        ['Green diproses', number_format($ringkasan['total_green'], 3, ',', '.'), 'kg'],
        ['Roasted dihasilkan', number_format($ringkasan['total_roasted'], 3, ',', '.'), 'kg'],
        ['Rata-rata yield', number_format($ringkasan['avg_yield'], 2, ',', '.'), '%'],
        ['Total waste/susut', number_format($ringkasan['total_waste'], 3, ',', '.'), 'kg'],
        ['Cost rata-rata / kg', 'Rp ' . number_format($ringkasan['avg_cost_per_kg'], 0, ',', '.'), ''],
    ] as [$label, $val, $unit])
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card h-100"><div class="card-body py-3">
            <div class="text-muted small">{{ $label }}</div>
            <div class="fw-bold fs-5">{{ $val }} <span class="fs-6 text-muted">{{ $unit }}</span></div>
        </div></div>
    </div>
    @endforeach
</div>

<div class="row g-3">
    <div class="col-12 col-lg-8">
        <div class="card"><div class="card-header fw-semibold small">Tren Yield (%) &amp; Waste (kg) — 30 hari terakhir</div>
        <div class="card-body"><canvas id="roasteryChart" height="110"></canvas></div></div>
    </div>
    <div class="col-12 col-lg-4">
        <div class="card"><div class="card-header fw-semibold small">Batch Terbaru</div>
        <ul class="list-group list-group-flush">
            @forelse($terbaru as $b)
            <li class="list-group-item small">
                <a href="{{ route('roastery.batch.show', $b) }}" class="fw-semibold text-decoration-none">{{ $b->nomor_batch }}</a>
                — {{ $b->greenBean->nama_item }} ({{ $b->profile->nama }})<br>
                <span class="text-muted">{{ (float) $b->green_qty_kg }} kg → {{ (float) $b->roasted_qty_kg }} kg · {{ (float) $b->yield_rate_percent }}% · {{ $b->status }}</span>
            </li>
            @empty
            <li class="list-group-item text-muted small">Belum ada batch.</li>
            @endforelse
        </ul></div>
    </div>
</div>

@push('scripts')
<script>
new Chart(document.getElementById('roasteryChart'), {
    type: 'bar',
    data: {
        labels: @json($labels),
        datasets: [
            { type: 'line', label: 'Yield (%)', data: @json($yield), borderColor: '#2D6A4F', backgroundColor: '#2D6A4F', yAxisID: 'y', spanGaps: true, tension: .2 },
            { type: 'bar', label: 'Waste (kg)', data: @json($waste), backgroundColor: '#F5C77E', yAxisID: 'y1' }
        ]
    },
    options: { scales: { y: { min: 0, max: 100, title: { display: true, text: 'Yield %' } }, y1: { position: 'right', beginAtZero: true, grid: { drawOnChartArea: false }, title: { display: true, text: 'Waste kg' } } } }
});
</script>
@endpush
@endsection
