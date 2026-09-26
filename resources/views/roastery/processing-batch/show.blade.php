@extends('layouts.app')

@section('title', $batch->kode_batch)

@section('content')
@php
    $badge = ['draft' => 'secondary', 'fermentasi' => 'warning', 'drying' => 'info', 'hulling' => 'info', 'sortir' => 'primary', 'selesai' => 'success', 'dibatalkan' => 'danger'][$batch->status];
    $labels = ['draft' => 'Draft', 'fermentasi' => 'Fermentasi', 'drying' => 'Drying', 'hulling' => 'Hulling', 'sortir' => 'Sortir', 'selesai' => 'Selesai', 'dibatalkan' => 'Dibatalkan'];
@endphp
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-droplet-half me-2 text-success"></i>{{ $batch->kode_batch }}
        <span class="badge bg-{{ $badge }} {{ $batch->status === 'fermentasi' ? 'text-dark' : '' }} ms-2">{{ $labels[$batch->status] }}</span></h5>
    <div class="d-flex gap-2 align-items-center">
        <x-panduan-button slug="processing-batch" />
        <a href="{{ route('processing-batch.index') }}" class="btn btn-outline-secondary btn-sm">Kembali</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-7">
        <div class="card"><div class="card-body">
            <table class="table table-sm mb-0">
                <tr><th style="width:220px">Tanggal Mulai</th><td>{{ $batch->tanggal_mulai->format('d/m/Y') }}</td></tr>
                <tr><th>Cherry</th><td>{{ $batch->cherryItem->nama_item }} — {{ number_format($batch->cherry_qty_kg, 3, ',', '.') }} kg</td></tr>
                <tr><th>Processing Method</th><td>{{ $batch->processing_method->label() }}</td></tr>
                @if($batch->cherry_cost_awal > 0)
                <tr><th>Cost Cherry Awal (FIFO)</th><td>Rp {{ number_format($batch->cherry_cost_awal, 0, ',', '.') }}</td></tr>
                @endif

                @if($batch->fermentasi_start)
                <tr><th>Fermentasi</th><td>
                    {{ $batch->fermentasi_start->format('d/m/Y H:i') }}
                    @if($batch->fermentasi_end) → {{ $batch->fermentasi_end->format('d/m/Y H:i') }} @endif
                    @if($batch->fermentasi_suhu_celsius) · {{ $batch->fermentasi_suhu_celsius }}°C @endif
                    @if($batch->fermentasi_catatan)<br><span class="text-muted small">{{ $batch->fermentasi_catatan }}</span>@endif
                </td></tr>
                @endif

                @if($batch->drying_start)
                <tr><th>Drying</th><td>
                    {{ $batch->drying_start->format('d/m/Y H:i') }}
                    @if($batch->drying_end) → {{ $batch->drying_end->format('d/m/Y H:i') }} @endif
                    @if($batch->drying_catatan)<br><span class="text-muted small">{{ $batch->drying_catatan }}</span>@endif
                </td></tr>
                @endif

                @if($batch->hulling_qty_kg)
                <tr><th>Hulling</th><td>{{ number_format($batch->hulling_qty_kg, 3, ',', '.') }} kg</td></tr>
                @endif

                @if($batch->status === 'selesai')
                <tr><th>Sortir</th><td>Final {{ number_format($batch->sortir_qty_kg, 3, ',', '.') }} kg, defect {{ number_format($batch->sortir_defect_kg, 3, ',', '.') }} kg</td></tr>
                <tr><th>Green Bean Output</th><td>{{ $batch->greenBean->nama_item }}</td></tr>
                <tr><th>Yield <x-tooltip key="processing-batch.yield_percent" /></th><td>{{ number_format($batch->yield_percent, 2, ',', '.') }}%</td></tr>
                <tr><th>Cost per kg Green</th><td>Rp {{ number_format($batch->cost_per_kg_green, 0, ',', '.') }}</td></tr>
                @endif

                @if($batch->catatan_umum)<tr><th>Catatan</th><td>{{ $batch->catatan_umum }}</td></tr>@endif
                <tr><th>Dibuat oleh</th><td>{{ $batch->user->name }}</td></tr>
            </table>
        </div></div>
    </div>

    <div class="col-12 col-lg-5">
        <div class="card"><div class="card-body">
            @if($batch->status === 'draft')
                <div class="small text-muted mb-2">Draft belum menggerakkan stok. Klik <b>Mulai Processing</b> untuk potong stok cherry & mulai tahap {{ $batch->processing_method->butuhFermentasi() ? 'Fermentasi' : 'Drying' }}.</div>
                @can('processing-batch.mulai')
                <form method="POST" action="{{ route('processing-batch.mulai', $batch) }}" onsubmit="return confirm('Mulai processing? Stok cherry akan dipotong.')" class="mb-2">
                    @csrf <button class="btn btn-success w-100">Mulai Processing</button>
                </form>
                @endcan

            @elseif($batch->status === 'fermentasi')
                @can('processing-batch.update-step')
                <form method="POST" action="{{ route('processing-batch.selesai-fermentasi', $batch) }}" class="mb-2">
                    @csrf
                    <label class="form-label small fw-semibold">Suhu Fermentasi (°C) <x-tooltip key="processing-batch.fermentasi_suhu" /></label>
                    <input type="number" name="fermentasi_suhu_celsius" class="form-control mb-2" min="0" max="60" placeholder="18-25">
                    <label class="form-label small fw-semibold">Catatan Fermentasi</label>
                    <textarea name="fermentasi_catatan" rows="2" class="form-control mb-2" maxlength="500"></textarea>
                    <button class="btn btn-primary w-100">Selesai Fermentasi → Lanjut Drying</button>
                </form>
                @endcan

            @elseif($batch->status === 'drying')
                @can('processing-batch.update-step')
                <form method="POST" action="{{ route('processing-batch.selesai-drying', $batch) }}" class="mb-2">
                    @csrf
                    <label class="form-label small fw-semibold">Catatan Drying</label>
                    <textarea name="drying_catatan" rows="2" class="form-control mb-2" maxlength="500" placeholder="mis. kadar air target tercapai"></textarea>
                    <button class="btn btn-primary w-100">Selesai Drying → Lanjut Hulling</button>
                </form>
                @endcan

            @elseif($batch->status === 'hulling')
                @can('processing-batch.update-step')
                <form method="POST" action="{{ route('processing-batch.selesai-hulling', $batch) }}" class="mb-2">
                    @csrf
                    <label class="form-label small fw-semibold">Qty Hasil Hulling (kg) *</label>
                    <input type="number" step="0.001" min="0.001" max="{{ $batch->cherry_qty_kg }}" name="hulling_qty_kg" class="form-control mb-2" required>
                    <div class="form-text mb-2">Berat green bean parchment setelah kulit ari dibuang (maks {{ (float) $batch->cherry_qty_kg }} kg).</div>
                    <button class="btn btn-primary w-100">Selesai Hulling → Lanjut Sortir</button>
                </form>
                @endcan

            @elseif($batch->status === 'sortir')
                @can('processing-batch.selesaikan')
                <form method="POST" action="{{ route('processing-batch.selesai-sortir', $batch) }}" class="mb-2">
                    @csrf
                    <label class="form-label small fw-semibold">Qty Final Green Bean (kg) *</label>
                    <input type="number" step="0.001" min="0.001" name="sortir_qty_kg" class="form-control mb-2" required>
                    <label class="form-label small fw-semibold">Qty Defect / Dibuang (kg) *</label>
                    <input type="number" step="0.001" min="0" name="sortir_defect_kg" class="form-control mb-2" value="0" required>
                    <div class="form-text mb-2">Total final + defect maks {{ $batch->hulling_qty_kg ? number_format($batch->hulling_qty_kg, 3, ',', '.') : '-' }} kg (hasil hulling).</div>
                    <button class="btn btn-success w-100">Selesai Sortir — Stok Green Bean Masuk</button>
                </form>
                @endcan

            @elseif($batch->status === 'selesai')
                <div class="text-success small"><i class="bi bi-check-circle-fill me-1"></i>Batch selesai, stok green bean sudah masuk RST001.</div>
            @else
                <div class="text-muted small">Batch ini sudah dibatalkan.</div>
            @endif

            @can('processing-batch.batalkan')
            @if(! in_array($batch->status, ['selesai', 'dibatalkan']))
            <form method="POST" action="{{ route('processing-batch.batalkan', $batch) }}" onsubmit="return confirm('Batalkan batch ini? Stok cherry yang sudah dipotong akan dikembalikan.')">
                @csrf
                <input type="text" name="reason" class="form-control form-control-sm mb-2" placeholder="Alasan pembatalan (opsional)">
                <button class="btn btn-outline-danger btn-sm w-100">Batalkan Batch</button>
            </form>
            @endif
            @endcan
        </div></div>
    </div>
</div>
@endsection
