@extends('layouts.app')

@section('title', 'Buat Closing')

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
    <div>
        <h5 class="fw-bold mb-0"><i class="bi bi-journal-plus me-2 text-primary"></i>Buat Closing</h5>
        <p class="text-muted mb-0 small">Tentukan periode & cakupan, lalu lihat preview angkanya sebelum disimpan</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('closing.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
        <x-panduan-button slug="closing-periode" />
    </div>
</div>

@if(session('error'))
<div class="alert alert-danger py-2">{{ session('error') }}</div>
@endif
@if($errors->any())
<div class="alert alert-danger py-2"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

@if(empty($cakupanOptions))
<div class="alert alert-warning">Akun Anda belum terhubung ke cabang mana pun, sehingga belum bisa membuat closing.</div>
@else
<div class="card" style="max-width:720px">
    <div class="card-body">
        <form method="GET" action="{{ route('closing.preview') }}" id="formClosing">
            <div class="mb-3">
                <label class="form-label fw-semibold">Jenis Closing <x-tooltip key="closing.jenis" /></label>
                <select name="jenis" id="jenis" class="form-select">
                    @foreach(\App\Models\ClosingPeriode::JENIS as $k => $v)
                    <option value="{{ $k }}" @selected($default['jenis'] === $k)>{{ $v }}</option>
                    @endforeach
                </select>
            </div>

            <div class="mb-2">
                <label class="form-label fw-semibold">Periode <x-tooltip key="closing.periode" /></label>
                <div class="d-flex flex-wrap gap-1 mb-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-preset="hari_ini">Hari ini</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-preset="kemarin">Kemarin</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-preset="minggu_ini">Minggu ini</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-preset="minggu_lalu">Minggu lalu</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-preset="bulan_ini">Bulan ini</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-preset="bulan_lalu">Bulan lalu</button>
                    <span class="d-inline-flex align-items-center gap-1 ms-1">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-preset="siklus">Siklus tgl</button>
                        <input type="number" id="tglSiklus" class="form-control form-control-sm" value="26" min="2" max="28" style="width:64px" title="Tanggal mulai siklus bulanan">
                    </span>
                </div>
                <div class="row g-2">
                    <div class="col-sm-6">
                        <label class="form-label small mb-1">Dari tanggal</label>
                        <input type="date" name="tanggal_mulai" id="tanggal_mulai" class="form-control" value="{{ $default['tanggal_mulai'] }}" max="{{ today()->toDateString() }}" required>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label small mb-1">Sampai tanggal</label>
                        <input type="date" name="tanggal_selesai" id="tanggal_selesai" class="form-control" value="{{ $default['tanggal_selesai'] }}" max="{{ today()->toDateString() }}" required>
                    </div>
                </div>
                <div class="form-text">Tanggal bisa diisi bebas (custom). Tombol di atas hanya jalan pintas — contoh "Siklus tgl 26" = 26 bulan lalu s/d 25 bulan ini.</div>
            </div>

            <div class="mb-3 mt-3">
                <label class="form-label fw-semibold">Cakupan <x-tooltip key="closing.cakupan" /></label>
                <select name="cakupan" class="form-select" required>
                    @foreach($cakupanOptions as $val => $label)
                    <option value="{{ $val }}" @selected((string) $default['cakupan'] === (string) $val)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1"></i>Lihat Preview</button>
        </form>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script>
(function () {
    const fmt = d => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    const today = new Date(); today.setHours(0, 0, 0, 0);
    const minD = (a, b) => a < b ? a : b;
    const set = (a, b, jenis) => {
        document.getElementById('tanggal_mulai').value = fmt(a);
        document.getElementById('tanggal_selesai').value = fmt(minD(b, today));
        if (jenis) document.getElementById('jenis').value = jenis;
    };
    const senin = d => { const x = new Date(d); const w = (x.getDay() + 6) % 7; x.setDate(x.getDate() - w); return x; };

    document.querySelectorAll('[data-preset]').forEach(btn => btn.addEventListener('click', () => {
        const t = new Date(today);
        switch (btn.dataset.preset) {
            case 'hari_ini': set(t, t, 'harian'); break;
            case 'kemarin': { const k = new Date(t); k.setDate(k.getDate() - 1); set(k, k, 'harian'); break; }
            case 'minggu_ini': { const s = senin(t); const e = new Date(s); e.setDate(e.getDate() + 6); set(s, e, 'mingguan'); break; }
            case 'minggu_lalu': { const s = senin(t); s.setDate(s.getDate() - 7); const e = new Date(s); e.setDate(e.getDate() + 6); set(s, e, 'mingguan'); break; }
            case 'bulan_ini': set(new Date(t.getFullYear(), t.getMonth(), 1), new Date(t.getFullYear(), t.getMonth() + 1, 0), 'bulanan'); break;
            case 'bulan_lalu': set(new Date(t.getFullYear(), t.getMonth() - 1, 1), new Date(t.getFullYear(), t.getMonth(), 0), 'bulanan'); break;
            case 'siklus': {
                const n = Math.min(28, Math.max(2, parseInt(document.getElementById('tglSiklus').value || '26', 10)));
                // Siklus terakhir yang sudah dimulai: n bulan lalu s/d (n-1) bulan ini, atau maju 1 bulan bila hari ini >= n.
                let s = new Date(t.getFullYear(), t.getMonth() - 1, n);
                if (t.getDate() >= n) s = new Date(t.getFullYear(), t.getMonth(), n);
                const e = new Date(s.getFullYear(), s.getMonth() + 1, n - 1);
                set(s, e, 'bulanan');
                break;
            }
        }
    }));
})();
</script>
@endpush
