@extends('layouts.app')

@section('title', 'Preview Closing')

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
        <h5 class="fw-bold mb-0"><i class="bi bi-eye me-2 text-primary"></i>Preview Closing {{ \App\Models\ClosingPeriode::JENIS[$jenis] }}</h5>
        <p class="text-muted mb-0 small">
            {{ $mulai->translatedFormat('d M Y') }}@if(!$mulai->equalTo($selesai)) s/d {{ $selesai->translatedFormat('d M Y') }}@endif
            · {{ $namaCakupan }} · dihitung {{ now()->translatedFormat('d M Y H:i') }}
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('closing.create', ['jenis' => $jenis, 'tanggal_mulai' => $mulai->toDateString(), 'tanggal_selesai' => $selesai->toDateString(), 'cakupan' => $cakupan]) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Ubah Periode</a>
        <x-panduan-button slug="closing-periode" />
    </div>
</div>

@if(session('error'))
<div class="alert alert-danger py-2">{{ session('error') }}</div>
@endif

<div class="alert alert-info py-2 small">
    <i class="bi bi-info-circle me-1"></i>Ini <strong>belum tersimpan</strong>. Angka di bawah dihitung dari data saat ini.
    Simpan sebagai <strong>Draft</strong> untuk diperiksa, atau <strong>Kunci (Final)</strong> agar angka tersimpan permanen dan transaksi di periode ini tidak bisa diubah lagi.
</div>

@if($bentrok)
<div class="alert alert-danger py-2">
    Periode ini bertumpuk dengan closing {{ $bentrok->labelJenis() }} Final
    <a href="{{ route('closing.show', $bentrok) }}">{{ $bentrok->kode }}</a> ({{ $bentrok->labelPeriode() }}).
    Closing ini hanya bisa disimpan sebagai draft sampai kunci closing tersebut dibuka.
</div>
@endif

@include('closing._snapshot', ['data' => $data])

<div class="card mt-3">
    <div class="card-body">
        <form method="POST" action="{{ route('closing.store') }}" id="formSimpanClosing">
            @csrf
            <input type="hidden" name="jenis" value="{{ $jenis }}">
            <input type="hidden" name="tanggal_mulai" value="{{ $mulai->toDateString() }}">
            <input type="hidden" name="tanggal_selesai" value="{{ $selesai->toDateString() }}">
            <input type="hidden" name="cakupan" value="{{ $cakupan }}">
            <div class="mb-3">
                <label class="form-label small fw-semibold">Catatan (opsional)</label>
                <textarea name="catatan" class="form-control" rows="2" maxlength="1000" placeholder="mis. Closing bulanan siklus 26–25, sudah cocok dengan uang fisik">{{ old('catatan') }}</textarea>
            </div>
            <div class="d-flex flex-wrap gap-2">
                @can('closing.create')
                <button type="submit" name="aksi" value="draft" class="btn btn-outline-primary"><i class="bi bi-save me-1"></i>Simpan sebagai Draft</button>
                @endcan
                @can('closing.finalize')
                @unless($bentrok)
                <button type="submit" name="aksi" value="final" class="btn btn-success" id="btnKunci"><i class="bi bi-lock-fill me-1"></i>Kunci Closing (Final)</button>
                @endunless
                @endcan
                <x-tooltip key="closing.kunci" />
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('btnKunci')?.addEventListener('click', function (e) {
    if (this.dataset.ok) return;
    e.preventDefault();
    const btn = this;
    Swal.fire({
        icon: 'warning',
        title: 'Kunci closing ini?',
        html: 'Angka akan disimpan permanen dan <b>transaksi di periode ini tidak bisa ditambah, diubah, atau dihapus</b> sampai Owner membuka kuncinya.',
        showCancelButton: true,
        confirmButtonText: 'Ya, kunci',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#2D6A4F',
    }).then(r => {
        if (r.isConfirmed) {
            btn.dataset.ok = '1';
            const hidden = document.createElement('input');
            hidden.type = 'hidden'; hidden.name = 'aksi'; hidden.value = 'final';
            document.getElementById('formSimpanClosing').appendChild(hidden);
            document.getElementById('formSimpanClosing').submit();
        }
    });
});
</script>
@endpush
