@extends('laporan.pdf.layout')

@section('content')
{{-- Sprint Analytics Laporan Penjualan (2026-10-05) — ringkasan 5 metrik di atas tabel detail. --}}
@if(!empty($salesAnalytics))
@php
    $sa = $salesAnalytics;
    $tanggalFmtPdf = fn ($t) => $t ? $t->translatedFormat('d M Y') : '-';
    $rupiahPdf = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
@endphp
<table class="data" style="margin-bottom:12px">
    <thead>
        <tr><th colspan="2" style="text-align:left;background:#f3f4f6">Ringkasan Analytics</th></tr>
    </thead>
    <tbody>
        <tr>
            <td style="width:40%">Rata-rata Nilai per Transaksi</td>
            <td class="num">{{ $sa['avg_tx_value'] !== null ? $rupiahPdf($sa['avg_tx_value']) : '—' }}</td>
        </tr>
        <tr>
            <td>Rata-rata Jumlah Transaksi</td>
            <td class="num">{{ $sa['avg_daily_tx'] !== null ? number_format($sa['avg_daily_tx'], 1, ',', '.') . ' tx/hari' : '—' }}</td>
        </tr>
        <tr>
            <td>Hari Omzet Tertinggi</td>
            <td class="num">
                @if($sa['top_value_day'])
                    {{ $rupiahPdf($sa['top_value_day']['omzet']) }}
                    ({{ $sa['top_value_day']['jumlah'] }} tx) &mdash; {{ $tanggalFmtPdf($sa['top_value_day']['tanggal']) }}
                @else — @endif
            </td>
        </tr>
        <tr>
            <td>Hari Transaksi Terbanyak</td>
            <td class="num">
                @if($sa['top_qty_day'])
                    {{ $sa['top_qty_day']['jumlah'] }} tx ({{ $rupiahPdf($sa['top_qty_day']['omzet']) }}) &mdash; {{ $tanggalFmtPdf($sa['top_qty_day']['tanggal']) }}
                @else — @endif
            </td>
        </tr>
        <tr>
            <td>Rata-rata Omzet Harian</td>
            <td class="num">{{ $sa['avg_daily_revenue'] !== null ? $rupiahPdf($sa['avg_daily_revenue']) : '—' }}</td>
        </tr>
    </tbody>
</table>
@endif

<table class="data">
    <thead>
        <tr>
            <th>No. Order</th>
            <th>Tanggal</th>
            <th>Cabang</th>
            <th>Pelanggan</th>
            <th>Tipe</th>
            <th class="num">Total Bayar</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        @forelse($orders as $order)
        <tr>
            <td>{{ $order->nomor_order }}</td>
            <td>{{ $order->tanggal_order?->format('d/m/Y') ?? '-' }}</td>
            <td>{{ $order->cabang?->nama_cabang ?? '-' }}</td>
            <td>{{ $order->nama_pelanggan ?? $order->pelanggan?->nama ?? 'Umum' }}</td>
            <td>{{ $order->tipe_order?->label() ?? '-' }}</td>
            <td class="num">Rp {{ number_format($order->total_bayar, 0, ',', '.') }}</td>
            <td>{{ $order->status?->label() ?? '-' }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="7" class="empty-note">Tidak ada data pada periode ini.</td>
        </tr>
        @endforelse
    </tbody>
    @if($orders->isNotEmpty())
    <tfoot>
        <tr class="grand">
            <td colspan="4">Total {{ $orders->count() }} transaksi</td>
            <td colspan="1"></td>
            <td class="num">Rp {{ number_format($totalOmzet, 0, ',', '.') }}</td>
            <td></td>
        </tr>
    </tfoot>
    @endif
</table>
@endsection
