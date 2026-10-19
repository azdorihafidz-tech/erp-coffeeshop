{{-- Sprint Closing Periode — tampilan isi snapshot. Dipakai halaman preview & detail. Variabel: $data --}}
@php
    $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
    $meta = $data['meta'] ?? [];
    $pj = $data['penjualan'] ?? [];
    $lr = $data['laba_rugi'] ?? [];
    $keu = $data['keuangan'] ?? [];
    $mut = $keu['mutasi'] ?? [];
    $kas = $data['kas'] ?? [];
    $stok = $data['stok'] ?? [];
    $konsolidasi = $meta['konsolidasi'] ?? false;
    $adaSetoran = ($mut['setoran_keluar_jumlah'] ?? 0) + ($mut['setoran_masuk_jumlah'] ?? 0) > 0;
@endphp

{{-- Ringkasan --}}
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
        <div class="card h-100"><div class="card-body py-3">
            <div class="small text-muted">Omzet</div>
            <div class="fs-5 fw-bold">{{ $rp($lr['omzet'] ?? 0) }}</div>
            <div class="small text-muted">{{ number_format($pj['jumlah_transaksi'] ?? 0, 0, ',', '.') }} transaksi</div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100"><div class="card-body py-3">
            <div class="small text-muted">Laba Kotor</div>
            <div class="fs-5 fw-bold">{{ $rp($lr['laba_kotor'] ?? 0) }}</div>
            <div class="small text-muted">HPP {{ $rp($lr['hpp'] ?? 0) }}</div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100"><div class="card-body py-3">
            <div class="small text-muted">Laba Bersih</div>
            <div class="fs-5 fw-bold {{ ($lr['laba_bersih'] ?? 0) < 0 ? 'text-danger' : 'text-success' }}">{{ $rp($lr['laba_bersih'] ?? 0) }}</div>
            <div class="small text-muted">Margin {{ ($lr['margin_bersih'] ?? null) !== null ? $lr['margin_bersih'] . '%' : '—' }}</div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100"><div class="card-body py-3">
            <div class="small text-muted">Total Kas Akhir</div>
            <div class="fs-5 fw-bold">{{ $rp($kas['total_akhir'] ?? 0) }}</div>
            <div class="small text-muted">Tunai {{ $rp($kas['total_tunai'] ?? 0) }} · Bank {{ $rp($kas['total_non_tunai'] ?? 0) }}</div>
        </div></div>
    </div>
</div>

@if(!empty($data['peringatan']))
<div class="alert alert-warning py-2">
    <div class="fw-semibold mb-1"><i class="bi bi-exclamation-triangle me-1"></i>Perlu dicek sebelum dikunci</div>
    <ul class="mb-0 small ps-3">
        @foreach($data['peringatan'] as $w)<li>{{ $w }}</li>@endforeach
    </ul>
</div>
@endif

<div class="row g-3">
    {{-- Laba Rugi --}}
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header fw-semibold"><i class="bi bi-graph-up me-1"></i>Laba Rugi Periode</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tr><td>Omzet kotor</td><td class="text-end">{{ $rp($pj['omzet_kotor'] ?? 0) }}</td></tr>
                    <tr><td class="ps-4 text-muted">Diskon</td><td class="text-end text-muted">−{{ $rp($pj['diskon'] ?? 0) }}</td></tr>
                    @if(($pj['service_charge'] ?? 0) + ($pj['take_away_fee'] ?? 0) > 0)
                    <tr><td class="ps-4 text-muted">Service charge + biaya takeaway</td><td class="text-end text-muted">+{{ $rp(($pj['service_charge'] ?? 0) + ($pj['take_away_fee'] ?? 0)) }}</td></tr>
                    @endif
                    <tr><td>Omzet bersih</td><td class="text-end">{{ $rp($lr['omzet_bersih'] ?? 0) }}</td></tr>
                    @if($konsolidasi && ($lr['eliminasi_omzet'] ?? 0) > 0)
                    <tr><td class="ps-4 text-muted">Eliminasi penjualan internal Roastery → outlet <x-tooltip key="closing.eliminasi" /></td><td class="text-end text-muted">−{{ $rp($lr['eliminasi_omzet']) }}</td></tr>
                    @endif
                    <tr class="fw-semibold"><td>Omzet</td><td class="text-end">{{ $rp($lr['omzet'] ?? 0) }}</td></tr>
                    <tr><td>HPP</td><td class="text-end">−{{ $rp($lr['hpp'] ?? 0) }}</td></tr>
                    <tr class="fw-semibold table-light"><td>Laba kotor @if(($lr['margin_kotor'] ?? null) !== null)<span class="text-muted small">({{ $lr['margin_kotor'] }}%)</span>@endif</td><td class="text-end">{{ $rp($lr['laba_kotor'] ?? 0) }}</td></tr>
                    <tr><td>Pemasukan lain</td><td class="text-end">+{{ $rp($lr['pemasukan_lain'] ?? 0) }}</td></tr>
                    <tr><td>Beban operasional</td><td class="text-end">−{{ $rp($lr['beban'] ?? 0) }}</td></tr>
                    <tr class="fw-bold table-success"><td>Laba bersih</td><td class="text-end">{{ $rp($lr['laba_bersih'] ?? 0) }}</td></tr>
                </table>
            </div>
        </div>
    </div>

    {{-- Penjualan --}}
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header fw-semibold"><i class="bi bi-receipt me-1"></i>Rincian Penjualan</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tr class="table-light"><td colspan="2" class="small fw-semibold">Per metode bayar</td></tr>
                    @forelse($pj['per_metode'] ?? [] as $r)
                    <tr><td>{{ $r['label'] }}</td><td class="text-end">{{ $rp($r['total']) }}</td></tr>
                    @empty
                    <tr><td colspan="2" class="text-muted small">Tidak ada penjualan</td></tr>
                    @endforelse
                    <tr class="table-light"><td colspan="2" class="small fw-semibold">Per tipe transaksi</td></tr>
                    @foreach($pj['per_tipe'] ?? [] as $r)
                    <tr><td>{{ $r['label'] }} <span class="text-muted small">({{ $r['jumlah'] }} trx)</span></td><td class="text-end">{{ $rp($r['total']) }}</td></tr>
                    @endforeach
                    @if(collect($pj['per_kanal'] ?? [])->where('kunci', '!=', 'retail')->isNotEmpty())
                    <tr class="table-light"><td colspan="2" class="small fw-semibold">Per kanal (Roastery)</td></tr>
                    @foreach($pj['per_kanal'] as $r)
                    <tr><td>{{ $r['label'] }} <span class="text-muted small">({{ $r['jumlah'] }} trx)</span></td><td class="text-end">{{ $rp($r['total']) }}</td></tr>
                    @endforeach
                    @endif
                    <tr><td class="text-muted small">Rata-rata per transaksi</td><td class="text-end text-muted small">{{ $rp($pj['rata_rata'] ?? 0) }}</td></tr>
                    @if(($pj['dibatalkan']['jumlah'] ?? 0) > 0)
                    <tr><td class="text-muted small">Order dibatalkan (tidak dihitung)</td><td class="text-end text-muted small">{{ $pj['dibatalkan']['jumlah'] }} trx · {{ $rp($pj['dibatalkan']['total']) }}</td></tr>
                    @endif
                </table>
            </div>
        </div>
    </div>

    {{-- Pemasukan & Pengeluaran --}}
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header fw-semibold"><i class="bi bi-cash-stack me-1"></i>Pemasukan Lain & Pengeluaran</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tr class="table-light"><td colspan="2" class="small fw-semibold">Pemasukan lain (di luar penjualan)</td></tr>
                    @forelse($keu['pemasukan_lain'] ?? [] as $r)
                    <tr><td>{{ $r['label'] }}</td><td class="text-end">{{ $rp($r['total']) }}</td></tr>
                    @empty
                    <tr><td colspan="2" class="text-muted small">—</td></tr>
                    @endforelse
                    <tr class="table-light"><td colspan="2" class="small fw-semibold">Beban operasional</td></tr>
                    @forelse($keu['beban'] ?? [] as $r)
                    <tr><td>{{ $r['label'] }}</td><td class="text-end">{{ $rp($r['total']) }}</td></tr>
                    @empty
                    <tr><td colspan="2" class="text-muted small">—</td></tr>
                    @endforelse
                    <tr class="table-light"><td colspan="2" class="small fw-semibold">Pembelian persediaan & aset <x-tooltip key="closing.persediaan" /></td></tr>
                    @forelse($keu['persediaan_aset'] ?? [] as $r)
                    <tr><td>{{ $r['label'] }}</td><td class="text-end">{{ $rp($r['total']) }}</td></tr>
                    @empty
                    <tr><td colspan="2" class="text-muted small">—</td></tr>
                    @endforelse
                </table>
            </div>
        </div>
    </div>

    {{-- Mutasi internal --}}
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header fw-semibold"><i class="bi bi-arrow-left-right me-1"></i>Mutasi Internal <span class="small text-muted fw-normal">(bukan pemasukan/pengeluaran)</span></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    @if($adaSetoran)
                    <tr class="table-light"><td colspan="2" class="small fw-semibold">Setoran / Perpindahan Dana antar cabang
                        @if($konsolidasi)<span class="badge bg-secondary ms-1">dieliminasi di konsolidasi</span>@endif</td></tr>
                    <tr><td>Setoran keluar — sudah diterima</td><td class="text-end">{{ $rp($mut['setoran_keluar_diterima'] ?? 0) }}</td></tr>
                    <tr><td>Setoran keluar — dalam perjalanan <x-tooltip key="closing.dalam_perjalanan" /></td><td class="text-end {{ ($mut['setoran_keluar_menunggu'] ?? 0) > 0 ? 'text-warning fw-semibold' : '' }}">{{ $rp($mut['setoran_keluar_menunggu'] ?? 0) }}</td></tr>
                    <tr><td>Setoran masuk — sudah diterima</td><td class="text-end">{{ $rp($mut['setoran_masuk_diterima'] ?? 0) }}</td></tr>
                    @if(($mut['setoran_masuk_menunggu'] ?? 0) > 0)
                    <tr><td>Setoran masuk — menunggu konfirmasi</td><td class="text-end text-warning">{{ $rp($mut['setoran_masuk_menunggu']) }}</td></tr>
                    @endif
                    @endif
                    <tr class="table-light"><td colspan="2" class="small fw-semibold">Di dalam cabang</td></tr>
                    <tr><td>Transfer antar kas <span class="text-muted small">({{ $mut['transfer_antar_kas_jumlah'] ?? 0 }}×)</span></td><td class="text-end">{{ $rp($mut['transfer_antar_kas'] ?? 0) }}</td></tr>
                    @if(($mut['setoran_kasir_keluar'] ?? 0) + ($mut['setoran_kasir_masuk'] ?? 0) > 0)
                    <tr><td>Setoran kasir keluar / masuk</td><td class="text-end">{{ $rp($mut['setoran_kasir_keluar'] ?? 0) }} / {{ $rp($mut['setoran_kasir_masuk'] ?? 0) }}</td></tr>
                    @endif
                    @if(($mut['pembelian_internal'] ?? 0) > 0)
                    <tr><td>Pembelian internal dari Roastery</td><td class="text-end">{{ $rp($mut['pembelian_internal']) }}</td></tr>
                    @endif
                </table>
            </div>
        </div>
    </div>

    {{-- Kas --}}
    <div class="col-12">
        <div class="card">
            <div class="card-header fw-semibold"><i class="bi bi-wallet2 me-1"></i>Posisi Kas</div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr><th>Kas</th>@if($konsolidasi)<th>Cabang</th>@endif<th>Jenis</th><th class="text-end">Saldo Awal</th><th class="text-end">Masuk</th><th class="text-end">Keluar</th><th class="text-end">Saldo Akhir</th></tr>
                    </thead>
                    <tbody>
                        @forelse($kas['detail'] ?? [] as $k)
                        <tr>
                            <td>{{ $k['nama_kas'] }}</td>
                            @if($konsolidasi)<td class="small">{{ $k['cabang'] }}</td>@endif
                            <td><span class="badge {{ $k['jenis'] === 'tunai' ? 'bg-success-subtle text-success' : 'bg-primary-subtle text-primary' }}">{{ $k['jenis'] === 'tunai' ? 'Tunai' : 'Bank/Non-tunai' }}</span></td>
                            <td class="text-end">{{ $rp($k['saldo_awal']) }}</td>
                            <td class="text-end text-success">{{ $rp($k['masuk']) }}</td>
                            <td class="text-end text-danger">{{ $rp($k['keluar']) }}</td>
                            <td class="text-end fw-semibold">{{ $rp($k['saldo_akhir']) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="7" class="text-center text-muted py-3">Belum ada akun kas di cakupan ini.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot class="table-light fw-semibold">
                        <tr><td colspan="{{ $konsolidasi ? 3 : 2 }}">Total</td><td class="text-end">{{ $rp($kas['total_awal'] ?? 0) }}</td><td class="text-end">{{ $rp($kas['total_masuk'] ?? 0) }}</td><td class="text-end">{{ $rp($kas['total_keluar'] ?? 0) }}</td><td class="text-end">{{ $rp($kas['total_akhir'] ?? 0) }}</td></tr>
                        @if(($keu['dalam_perjalanan'] ?? 0) > 0)
                        <tr><td colspan="{{ $konsolidasi ? 6 : 5 }}" class="text-muted fw-normal">+ Dalam perjalanan (setoran belum diterima)</td><td class="text-end text-warning">{{ $rp($keu['dalam_perjalanan']) }}</td></tr>
                        @endif
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    {{-- Top produk --}}
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header fw-semibold"><i class="bi bi-trophy me-1"></i>10 Produk Teratas</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Produk</th><th class="text-end">Qty</th><th class="text-end">Omzet</th><th class="text-end">Laba</th></tr></thead>
                    <tbody>
                        @forelse($pj['top_produk'] ?? [] as $p)
                        <tr><td>{{ $p['nama'] }}</td><td class="text-end">{{ rtrim(rtrim(number_format($p['qty'], 3, ',', '.'), '0'), ',') }}</td><td class="text-end">{{ $rp($p['omzet']) }}</td><td class="text-end">{{ $rp($p['omzet'] - $p['hpp']) }}</td></tr>
                        @empty
                        <tr><td colspan="4" class="text-muted small text-center">—</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Stok --}}
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header fw-semibold"><i class="bi bi-box-seam me-1"></i>Nilai Stok Akhir Periode <x-tooltip key="closing.stok" /></div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Lokasi</th><th class="text-end">Jumlah Item</th><th class="text-end">Nilai</th></tr></thead>
                    <tbody>
                        @forelse($stok['per_lokasi'] ?? [] as $s)
                        <tr><td>{{ $s['lokasi'] }}</td><td class="text-end">{{ $s['jumlah_item'] }}</td><td class="text-end">{{ $rp($s['nilai']) }}</td></tr>
                        @empty
                        <tr><td colspan="3" class="text-muted small text-center">Tidak ada stok tercatat</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot class="table-light fw-semibold"><tr><td colspan="2">Total</td><td class="text-end">{{ $rp($stok['total_nilai'] ?? 0) }}</td></tr></tfoot>
                </table>
            </div>
            @if(!empty($stok['detail']))
            <div class="card-footer py-2">
                <a class="small" data-bs-toggle="collapse" href="#stokDetail" role="button">Lihat rincian per item ({{ count($stok['detail']) }})</a>
            </div>
            <div class="collapse" id="stokDetail">
                <div class="table-responsive" style="max-height:360px">
                    <table class="table table-sm mb-0 small">
                        <thead class="table-light"><tr><th>Lokasi</th><th>Item</th><th class="text-end">Qty</th><th class="text-end">Harga</th><th class="text-end">Nilai</th></tr></thead>
                        <tbody>
                            @foreach($stok['detail'] as $s)
                            <tr><td>{{ $s['lokasi'] }}</td><td>{{ $s['nama'] }}</td><td class="text-end">{{ rtrim(rtrim(number_format($s['qty'], 3, ',', '.'), '0'), ',') }} {{ $s['satuan'] }}</td><td class="text-end">{{ $rp($s['harga']) }}</td><td class="text-end">{{ $rp($s['nilai']) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
