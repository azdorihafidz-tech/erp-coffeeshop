@extends('layouts.app')

@section('title', 'Pengaturan Umum')

@section('content')

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="fw-bold mb-0" style="color:#1e293b">
            <i class="bi bi-building me-2 text-primary"></i>Pengaturan Umum
        </h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0" style="font-size:0.8rem">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item active">Pengaturan Umum</li>
            </ol>
        </nav>
    </div>
    <x-panduan-button slug="pengaturan-umum" />
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
    <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<form method="POST" action="{{ route('pengaturan.umum.update') }}" enctype="multipart/form-data" id="formPengaturanUmum">
    @csrf @method('PUT')

    <div class="row g-4">
        {{-- Kolom Kiri --}}
        <div class="col-12 col-lg-8">
            <div class="card mb-4">
                <div class="card-header py-3 px-4">
                    <i class="bi bi-info-circle me-2 text-primary"></i>
                    <span class="fw-semibold">Identitas Usaha</span>
                </div>
                <div class="card-body p-4">

                    <div class="mb-3">
                        <label for="nama_perusahaan" class="form-label fw-medium">
                            Nama Usaha <span class="text-danger">*</span>
                        </label>
                        <input type="text" id="nama_perusahaan" name="nama_perusahaan"
                            class="form-control @error('nama_perusahaan') is-invalid @enderror"
                            value="{{ old('nama_perusahaan', $setting->nama_perusahaan) }}"
                            maxlength="100" required>
                        <div class="form-text">Nama lengkap — tampil di halaman login, header laporan PDF, menu QR pelanggan, label cup dan TV Antrian.</div>
                        @error('nama_perusahaan')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-5">
                            <label for="nama_singkat" class="form-label fw-medium">
                                Nama Singkat <x-tooltip key="pengaturan_umum.nama_singkat" placement="top" />
                            </label>
                            <input type="text" id="nama_singkat" name="nama_singkat"
                                class="form-control @error('nama_singkat') is-invalid @enderror"
                                value="{{ old('nama_singkat', $setting->nama_singkat) }}"
                                maxlength="30" placeholder="Kosong = pakai Nama Usaha">
                            @error('nama_singkat')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-7">
                            <label for="tagline" class="form-label fw-medium">
                                Tagline <x-tooltip key="pengaturan_umum.tagline" placement="top" />
                            </label>
                            <input type="text" id="tagline" name="tagline"
                                class="form-control @error('tagline') is-invalid @enderror"
                                value="{{ old('tagline', $setting->tagline) }}"
                                maxlength="150" placeholder="Contoh: Coffee Shop & Roastery">
                            @error('tagline')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="alamat_perusahaan" class="form-label fw-medium">Alamat</label>
                        <textarea id="alamat_perusahaan" name="alamat_perusahaan" rows="3"
                            class="form-control @error('alamat_perusahaan') is-invalid @enderror"
                            maxlength="1000"
                            placeholder="Jl. Contoh No. 1, Kota...">{{ old('alamat_perusahaan', $setting->alamat_perusahaan) }}</textarea>
                        @error('alamat_perusahaan')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-0">
                        <label for="telepon" class="form-label fw-medium">No. Telepon / WhatsApp</label>
                        <input type="text" id="telepon" name="telepon"
                            class="form-control @error('telepon') is-invalid @enderror"
                            value="{{ old('telepon', $setting->telepon) }}"
                            maxlength="30" placeholder="Contoh: 0812-3456-7890">
                        <div class="form-text">Tampil di header laporan PDF.</div>
                        @error('telepon')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                </div>
            </div>

            <div class="card">
                <div class="card-header py-3 px-4">
                    <i class="bi bi-image me-2 text-primary"></i>
                    <span class="fw-semibold">Logo Usaha</span>
                    <x-tooltip key="pengaturan_umum.logo" placement="top" />
                </div>
                <div class="card-body p-4">

                    <div class="mb-3 p-3 border rounded-3 bg-light d-flex align-items-center gap-3 flex-wrap">
                        <img src="{{ $brand->logoUrl() }}" alt="Logo saat ini" id="logoPreview"
                             style="max-height:80px;max-width:200px;object-fit:contain;">
                        <img src="{{ $brand->ikonUrl(96) }}" alt="Ikon aplikasi"
                             style="width:48px;height:48px;object-fit:contain;border-radius:10px;background:#fff;border:1px solid #e2e8f0">
                        <div>
                            @if($brand->adaLogoUpload())
                                <div class="fw-medium" style="font-size:0.875rem">Logo aktif</div>
                                <div class="text-muted" style="font-size:0.75rem">Kiri: logo · Kanan: ikon aplikasi &amp; favicon (dibuat otomatis)</div>
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" name="hapus_logo" id="hapusLogo" value="1">
                                    <label class="form-check-label text-danger" for="hapusLogo" style="font-size:0.8rem">
                                        Hapus logo ini (kembali ke logo bawaan)
                                    </label>
                                </div>
                            @else
                                <div class="fw-medium" style="font-size:0.875rem">Logo bawaan program</div>
                                <div class="text-muted" style="font-size:0.75rem">Upload logo usaha Anda supaya tampil di semua halaman.</div>
                            @endif
                        </div>
                    </div>

                    <div>
                        <label for="logo" class="form-label fw-medium">
                            {{ $brand->adaLogoUpload() ? 'Ganti Logo' : 'Upload Logo' }}
                        </label>
                        <input type="file" id="logo" name="logo" accept="image/png,image/jpeg,image/webp"
                            class="form-control @error('logo') is-invalid @enderror"
                            onchange="previewLogo(this)">
                        <div class="form-text">Format: PNG, JPG, WebP. Maksimal 2MB. Paling bagus: PNG latar transparan, bentuk persegi atau bulat. Ikon aplikasi &amp; favicon dibuat otomatis dari logo ini.</div>
                        @error('logo')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                </div>
            </div>

            {{-- 4.54 Warna Tampilan --}}
            @php
                $bawaan = \App\Support\Branding::WARNA_BAWAAN;
                $nilaiWarna = [];
                foreach (['utama', 'sidebar', 'latar'] as $k) {
                    $nilaiWarna[$k] = old('warna_' . $k, $setting->getAttribute('warna_' . $k));
                }
                $labelWarna = [
                    'utama'   => ['Warna Utama', 'Tombol, judul, ikon, link, grafik & menu aktif'],
                    'sidebar' => ['Warna Sidebar', 'Sidebar kiri & bagian gelap halaman login'],
                    'latar'   => ['Warna Latar', 'Latar belakang halaman'],
                ];
            @endphp
            <div class="card mt-4" id="cardWarna">
                <div class="card-header py-3 px-4">
                    <i class="bi bi-palette me-2 text-primary"></i>
                    <span class="fw-semibold">Warna Tampilan</span>
                    <x-tooltip key="pengaturan_umum.warna" placement="top" />
                    <span class="badge ms-2 {{ $brand->warnaDiatur() ? 'bg-primary' : 'bg-secondary' }}" style="font-size:.7rem">
                        {{ $brand->warnaDiatur() ? 'Warna sendiri' : 'Warna bawaan' }}
                    </span>
                </div>
                <div class="card-body p-4">
                    <input type="hidden" name="reset_warna" id="resetWarna" value="0">

                    <div class="fw-medium mb-2" style="font-size:.875rem">Tema siap pakai</div>
                    <div class="d-flex flex-wrap gap-2 mb-4">
                        @foreach(\App\Support\Branding::TEMA as $kode => $tema)
                            <button type="button" class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-2 btn-tema"
                                    data-utama="{{ $tema['utama'] }}" data-sidebar="{{ $tema['sidebar'] }}" data-latar="{{ $tema['latar'] }}">
                                <span class="d-inline-flex rounded overflow-hidden border" style="height:18px">
                                    <span style="width:12px;background:{{ $tema['sidebar'] }}"></span>
                                    <span style="width:12px;background:{{ $tema['utama'] }}"></span>
                                    <span style="width:12px;background:{{ $tema['latar'] }}"></span>
                                </span>
                                {{ $tema['nama'] }}
                            </button>
                        @endforeach
                    </div>

                    <div class="row g-4">
                        <div class="col-md-6">
                            @foreach($labelWarna as $k => [$judul, $ket])
                                <div class="mb-3">
                                    <label class="form-label fw-medium mb-1" for="warna_{{ $k }}">{{ $judul }}</label>
                                    <div class="input-group">
                                        <input type="color" class="form-control form-control-color pilih-warna" id="pilih_{{ $k }}"
                                               style="flex:0 0 3.5rem;max-width:3.5rem"
                                               data-target="warna_{{ $k }}"
                                               value="{{ \App\Support\Branding::hexValid($nilaiWarna[$k]) ? strtoupper($nilaiWarna[$k]) : $bawaan[$k] }}"
                                               title="Pilih {{ strtolower($judul) }}">
                                        <input type="text" class="form-control font-monospace teks-warna @error('warna_' . $k) is-invalid @enderror"
                                               id="warna_{{ $k }}" name="warna_{{ $k }}" maxlength="7"
                                               value="{{ $nilaiWarna[$k] }}" data-bawaan="{{ $bawaan[$k] }}"
                                               placeholder="Bawaan {{ $bawaan[$k] }}">
                                        @error('warna_' . $k)
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="form-text">{{ $ket }}. Kosong = bawaan.</div>
                                </div>
                            @endforeach
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnWarnaBawaan">
                                <i class="bi bi-arrow-counterclockwise me-1"></i>Kembalikan ke bawaan
                            </button>
                        </div>

                        <div class="col-md-6">
                            <div class="fw-medium mb-1" style="font-size:.875rem">Contoh tampilan</div>
                            <div class="d-flex border rounded-3 overflow-hidden" style="height:190px" id="contohWarna">
                                <div class="p-2 d-flex flex-column gap-1" style="width:38%;background:var(--cw-sidebar);color:#fff;font-size:.7rem">
                                    <div class="fw-bold mb-1" style="font-size:.75rem">{{ $brand->namaSingkat() }}</div>
                                    <div class="rounded px-2 py-1" style="background:var(--cw-utama)"><i class="bi bi-cart3 me-1"></i>POS</div>
                                    <div class="px-2 py-1" style="opacity:.75"><i class="bi bi-box-seam me-1"></i>Stok</div>
                                    <div class="px-2 py-1" style="opacity:.75"><i class="bi bi-graph-up me-1"></i>Laporan</div>
                                </div>
                                <div class="flex-grow-1 p-2" style="background:var(--cw-latar)">
                                    <div class="bg-white rounded-2 p-2 shadow-sm h-100" style="font-size:.72rem;color:#1A1A1A">
                                        <div class="fw-bold mb-1" style="color:var(--cw-utama);font-size:.8rem"><i class="bi bi-receipt me-1"></i>Laporan Penjualan</div>
                                        <div class="mb-2">Omzet hari ini <strong>Rp 1.250.000</strong></div>
                                        <span class="d-inline-block rounded px-2 py-1 mb-2" style="background:var(--cw-utama);color:#fff">Simpan</span>
                                        <span class="d-inline-block rounded px-2 py-1 mb-2" style="border:1px solid var(--cw-utama);color:var(--cw-utama)">Batal</span>
                                        <div><a href="javascript:void(0)" style="color:var(--cw-utama)">Lihat detail</a></div>
                                    </div>
                                </div>
                            </div>
                            <div id="peringatanWarna" class="small mt-2"></div>
                            <div class="form-text">Warna merah (hapus/peringatan), hijau (sukses) dan kuning (perhatian) sengaja tidak ikut berubah. Struk &amp; label tetap hitam-putih.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Kolom Kanan --}}
        <div class="col-12 col-lg-4">

            <div class="card mb-3">
                <div class="card-header py-3 px-4">
                    <i class="bi bi-info-circle me-2 text-muted"></i>
                    <span class="fw-semibold">Dipakai di mana?</span>
                </div>
                <div class="card-body p-4" style="font-size:0.8rem">
                    <p class="text-muted mb-2">
                        <i class="bi bi-box-arrow-in-right me-1 text-primary"></i>
                        <strong>Login, sidebar &amp; judul tab</strong> — logo, nama singkat &amp; tagline.
                    </p>
                    <p class="text-muted mb-2">
                        <i class="bi bi-printer me-1 text-primary"></i>
                        <strong>Struk, label cup &amp; laporan PDF</strong> — nama usaha (footer struk per cabang diatur di Cabang → Edit).
                    </p>
                    <p class="text-muted mb-2">
                        <i class="bi bi-qr-code me-1 text-primary"></i>
                        <strong>Menu QR pelanggan &amp; TV Antrian</strong> — logo &amp; nama usaha.
                    </p>
                    <p class="text-muted mb-0">
                        <i class="bi bi-phone me-1 text-primary"></i>
                        <strong>Ikon aplikasi (PWA) &amp; aplikasi kasir</strong> — ikon dibuat otomatis dari logo.
                    </p>
                </div>
            </div>

            <div class="card">
                <div class="card-body p-4">
                    <button type="submit" class="btn btn-primary w-100 mb-2">
                        <i class="bi bi-check-lg me-2"></i>Simpan Pengaturan
                    </button>
                    <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary w-100">
                        <i class="bi bi-x-lg me-2"></i>Batal
                    </a>
                </div>
            </div>

        </div>
    </div>

</form>

@endsection

@push('scripts')
<script>
function previewLogo(input) {
    const preview = document.getElementById('logoPreview');
    if (input.files && input.files[0] && preview) {
        const reader = new FileReader();
        reader.onload = function (e) { preview.src = e.target.result; };
        reader.readAsDataURL(input.files[0]);
    }
}

// 4.54 Warna Tampilan — contoh langsung + cek keterbacaan (aturan sama dengan server, Branding::cekKeterbacaan)
(function () {
    const KUNCI = ['utama', 'sidebar', 'latar'];
    const BATAS = {
        utamaPutih: {{ \App\Support\Branding::KONTRAS_UTAMA_PUTIH }},
        utamaLatar: {{ \App\Support\Branding::KONTRAS_UTAMA_LATAR }},
        sidebarPutih: {{ \App\Support\Branding::KONTRAS_SIDEBAR_PUTIH }},
        latarTeks: {{ \App\Support\Branding::KONTRAS_LATAR_TEKS }},
    };
    const hexValid = v => /^#[0-9A-Fa-f]{6}$/.test(v || '');
    const teks = k => document.getElementById('warna_' + k);
    const pilih = k => document.getElementById('pilih_' + k);
    const efektif = k => hexValid(teks(k).value.trim()) ? teks(k).value.trim().toUpperCase() : teks(k).dataset.bawaan;

    function lum(hex) {
        const c = [1, 3, 5].map(i => parseInt(hex.substr(i, 2), 16) / 255)
            .map(v => v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4));
        return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
    }
    function kontras(a, b) {
        const la = lum(a), lb = lum(b);
        return (Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05);
    }

    function perbarui() {
        const w = {}; KUNCI.forEach(k => w[k] = efektif(k));
        const box = document.getElementById('contohWarna');
        box.style.setProperty('--cw-utama', w.utama);
        box.style.setProperty('--cw-sidebar', w.sidebar);
        box.style.setProperty('--cw-latar', w.latar);

        const pesan = [];
        KUNCI.forEach(k => {
            const v = teks(k).value.trim();
            if (v !== '' && !hexValid(v)) pesan.push('Kode ' + k + ' harus seperti ' + teks(k).dataset.bawaan + '.');
        });
        if (kontras(w.utama, '#FFFFFF') < BATAS.utamaPutih) pesan.push('Warna utama terlalu terang — tulisan putih di tombol tidak terbaca.');
        else if (kontras(w.utama, w.latar) < BATAS.utamaLatar) pesan.push('Warna utama terlalu mirip dengan warna latar.');
        if (kontras(w.sidebar, '#FFFFFF') < BATAS.sidebarPutih) pesan.push('Warna sidebar terlalu terang — tulisan menu tidak terbaca.');
        if (kontras(w.latar, '#1A1A1A') < BATAS.latarTeks) pesan.push('Warna latar terlalu gelap — tulisan halaman tidak terbaca.');

        const el = document.getElementById('peringatanWarna');
        el.className = 'small mt-2 ' + (pesan.length ? 'text-danger' : 'text-success');
        el.innerHTML = pesan.length
            ? '<i class="bi bi-exclamation-triangle-fill me-1"></i>' + pesan.join(' ') + ' <strong>Tidak bisa disimpan</strong> — pilih warna lain.'
            : '<i class="bi bi-check-circle-fill me-1"></i>Mudah dibaca.';
    }

    document.querySelectorAll('.pilih-warna').forEach(p => p.addEventListener('input', function () {
        document.getElementById(this.dataset.target).value = this.value.toUpperCase();
        document.getElementById('resetWarna').value = '0';
        perbarui();
    }));
    document.querySelectorAll('.teks-warna').forEach(t => t.addEventListener('input', function () {
        const k = this.id.replace('warna_', '');
        if (hexValid(this.value.trim())) pilih(k).value = this.value.trim();
        document.getElementById('resetWarna').value = '0';
        perbarui();
    }));
    document.querySelectorAll('.btn-tema').forEach(b => b.addEventListener('click', function () {
        KUNCI.forEach(k => { teks(k).value = this.dataset[k]; pilih(k).value = this.dataset[k]; });
        document.getElementById('resetWarna').value = '0';
        perbarui();
    }));
    document.getElementById('btnWarnaBawaan').addEventListener('click', function () {
        KUNCI.forEach(k => { teks(k).value = ''; pilih(k).value = teks(k).dataset.bawaan; });
        document.getElementById('resetWarna').value = '1';
        perbarui();
    });
    perbarui();
})();
</script>
@endpush
