@extends('laporan.pdf.layout')

@section('content')
@php
    $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
    $pj = $data['penjualan'] ?? [];
    $lr = $data['laba_rugi'] ?? [];
    $keu = $data['keuangan'] ?? [];
    $mut = $keu['mutasi'] ?? [];
    $kas = $data['kas'] ?? [];
    $stok = $data['stok'] ?? [];
    $konsolidasi = $data['meta']['konsolidasi'] ?? false;
@endphp

@if($closing->status !== 'final')
<p style="color:#b45309;font-weight:bold;margin-bottom:8px">
    Status: {{ strtoupper($closing->status) }} — {{ $closing->status === 'draft' ? 'belum dikunci, angka masih bisa berubah.' : 'kunci sudah dibuka (' . $closing->alasan_buka . ').' }}
</p>
@endif

<table class="data">
    <thead><tr><th colspan="2">Laba Rugi Periode</th></tr></thead>
    <tbody>
        <tr><td>Jumlah transaksi</td><td class="num">{{ number_format($pj['jumlah_transaksi'] ?? 0, 0, ',', '.') }}</td></tr>
        <tr><td>Omzet kotor</td><td class="num">{{ $rp($pj['omzet_kotor'] ?? 0) }}</td></tr>
        <tr><td>Diskon</td><td class="num">-{{ $rp($pj['diskon'] ?? 0) }}</td></tr>
        <tr><td>Omzet bersih</td><td class="num">{{ $rp($lr['omzet_bersih'] ?? 0) }}</td></tr>
        @if($konsolidasi && ($lr['eliminasi_omzet'] ?? 0) > 0)
        <tr><td>Eliminasi penjualan internal</td><td class="num">-{{ $rp($lr['eliminasi_omzet']) }}</td></tr>
        @endif
        <tr class="subtotal"><td>Omzet</td><td class="num">{{ $rp($lr['omzet'] ?? 0) }}</td></tr>
        <tr><td>HPP</td><td class="num">-{{ $rp($lr['hpp'] ?? 0) }}</td></tr>
        <tr class="subtotal"><td>Laba kotor</td><td class="num">{{ $rp($lr['laba_kotor'] ?? 0) }}</td></tr>
        <tr><td>Pemasukan lain</td><td class="num">{{ $rp($lr['pemasukan_lain'] ?? 0) }}</td></tr>
        <tr><td>Beban operasional</td><td class="num">-{{ $rp($lr['beban'] ?? 0) }}</td></tr>
        <tr class="grand"><td>Laba bersih</td><td class="num">{{ $rp($lr['laba_bersih'] ?? 0) }}</td></tr>
    </tbody>
</table>

<table class="data">
    <thead><tr><th>Metode Bayar</th><th class="num">Total</th></tr></thead>
    <tbody>
        @forelse($pj['per_metode'] ?? [] as $r)
        <tr><td>{{ $r['label'] }}</td><td class="num">{{ $rp($r['total']) }}</td></tr>
        @empty
        <tr><td colspan="2" class="empty-note">Tidak ada penjualan</td></tr>
        @endforelse
    </tbody>
</table>

<table class="data">
    <thead><tr><th>Kelompok</th><th>Kategori</th><th class="num">Jumlah</th></tr></thead>
    <tbody>
        @foreach($keu['pemasukan_lain'] ?? [] as $r)
        <tr><td>Pemasukan lain</td><td>{{ $r['label'] }}</td><td class="num">{{ $rp($r['total']) }}</td></tr>
        @endforeach
        @foreach($keu['beban'] ?? [] as $r)
        <tr><td>Beban operasional</td><td>{{ $r['label'] }}</td><td class="num">{{ $rp($r['total']) }}</td></tr>
        @endforeach
        @foreach($keu['persediaan_aset'] ?? [] as $r)
        <tr><td>Pembelian persediaan/aset</td><td>{{ $r['label'] }}</td><td class="num">{{ $rp($r['total']) }}</td></tr>
        @endforeach
        <tr class="section-head"><td colspan="3">Mutasi internal (bukan pemasukan/pengeluaran)</td></tr>
        <tr><td>Mutasi</td><td>Setoran keluar — diterima</td><td class="num">{{ $rp($mut['setoran_keluar_diterima'] ?? 0) }}</td></tr>
        <tr><td>Mutasi</td><td>Setoran keluar — dalam perjalanan</td><td class="num">{{ $rp($mut['setoran_keluar_menunggu'] ?? 0) }}</td></tr>
        <tr><td>Mutasi</td><td>Setoran masuk — diterima</td><td class="num">{{ $rp($mut['setoran_masuk_diterima'] ?? 0) }}</td></tr>
        <tr><td>Mutasi</td><td>Transfer antar kas</td><td class="num">{{ $rp($mut['transfer_antar_kas'] ?? 0) }}</td></tr>
    </tbody>
</table>

<table class="data">
    <thead><tr><th>Kas</th>@if($konsolidasi)<th>Cabang</th>@endif<th class="num">Saldo Awal</th><th class="num">Masuk</th><th class="num">Keluar</th><th class="num">Saldo Akhir</th></tr></thead>
    <tbody>
        @forelse($kas['detail'] ?? [] as $k)
        <tr><td>{{ $k['nama_kas'] }}</td>@if($konsolidasi)<td>{{ $k['cabang'] }}</td>@endif<td class="num">{{ $rp($k['saldo_awal']) }}</td><td class="num">{{ $rp($k['masuk']) }}</td><td class="num">{{ $rp($k['keluar']) }}</td><td class="num">{{ $rp($k['saldo_akhir']) }}</td></tr>
        @empty
        <tr><td colspan="6" class="empty-note">Tidak ada kas</td></tr>
        @endforelse
        <tr class="subtotal"><td colspan="{{ $konsolidasi ? 2 : 1 }}">Total (Tunai {{ $rp($kas['total_tunai'] ?? 0) }} · Bank {{ $rp($kas['total_non_tunai'] ?? 0) }})</td><td class="num">{{ $rp($kas['total_awal'] ?? 0) }}</td><td class="num">{{ $rp($kas['total_masuk'] ?? 0) }}</td><td class="num">{{ $rp($kas['total_keluar'] ?? 0) }}</td><td class="num">{{ $rp($kas['total_akhir'] ?? 0) }}</td></tr>
        @if(($keu['dalam_perjalanan'] ?? 0) > 0)
        <tr><td colspan="{{ $konsolidasi ? 5 : 4 }}">+ Dalam perjalanan (setoran belum diterima)</td><td class="num">{{ $rp($keu['dalam_perjalanan']) }}</td></tr>
        @endif
    </tbody>
</table>

<table class="data">
    <thead><tr><th>Produk Teratas</th><th class="num">Qty</th><th class="num">Omzet</th><th class="num">Laba</th></tr></thead>
    <tbody>
        @forelse($pj['top_produk'] ?? [] as $p)
        <tr><td>{{ $p['nama'] }}</td><td class="num">{{ rtrim(rtrim(number_format($p['qty'], 3, ',', '.'), '0'), ',') }}</td><td class="num">{{ $rp($p['omzet']) }}</td><td class="num">{{ $rp($p['omzet'] - $p['hpp']) }}</td></tr>
        @empty
        <tr><td colspan="4" class="empty-note">—</td></tr>
        @endforelse
    </tbody>
</table>

<table class="data">
    <thead><tr><th>Nilai Stok Akhir (estimasi)</th><th class="num">Jumlah Item</th><th class="num">Nilai</th></tr></thead>
    <tbody>
        @forelse($stok['per_lokasi'] ?? [] as $s)
        <tr><td>{{ $s['lokasi'] }}</td><td class="num">{{ $s['jumlah_item'] }}</td><td class="num">{{ $rp($s['nilai']) }}</td></tr>
        @empty
        <tr><td colspan="3" class="empty-note">Tidak ada stok tercatat</td></tr>
        @endforelse
        <tr class="subtotal"><td colspan="2">Total</td><td class="num">{{ $rp($stok['total_nilai'] ?? 0) }}</td></tr>
    </tbody>
</table>

@if(!empty($data['peringatan']))
<table class="data">
    <thead><tr><th>Catatan saat closing</th></tr></thead>
    <tbody>
        @foreach($data['peringatan'] as $w)<tr class="highlight-warning"><td>{{ $w }}</td></tr>@endforeach
    </tbody>
</table>
@endif
@endsection
