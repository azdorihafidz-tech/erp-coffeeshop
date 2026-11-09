{{--
    4.54 Warna Tampilan — variabel warna usaha dari Pengaturan Umum.
    Pasang SESUDAH CSS Bootstrap. Selama warna belum diatur (bawaan), hanya
    variabel --kd-* yang ditulis (nilainya sama dengan warna lama) sehingga
    tampilan tidak berubah sedikit pun. Begitu Owner memilih warna, tombol,
    ikon, link & komponen "primary" Bootstrap ikut warna utama.
--}}
@php
    $u = $brand->warnaUtama();
    $s = $brand->warnaSidebar();
    $l = $brand->warnaLatar();
    $uGelap = $brand->warnaUtamaGelap(0.15);
    $uLebihGelap = $brand->warnaUtamaGelap(0.25);
    $rgb = \App\Support\Branding::rgbTeks($u);
@endphp
<style>
    :root {
        --kd-utama: {{ $u }};
        --kd-utama-gelap: {{ $uGelap }};
        --kd-utama-rgb: {{ $rgb }};
        --kd-sidebar: {{ $s }};
        --kd-latar: {{ $l }};
    }
@if($brand->warnaDiatur())
    :root {
        --bs-primary: {{ $u }};
        --bs-primary-rgb: {{ $rgb }};
        --bs-primary-text-emphasis: {{ $uLebihGelap }};
        --bs-primary-bg-subtle: {{ \App\Support\Branding::campur($u, '#FFFFFF', 0.85) }};
        --bs-primary-border-subtle: {{ \App\Support\Branding::campur($u, '#FFFFFF', 0.6) }};
        --bs-link-color: {{ $u }};
        --bs-link-color-rgb: {{ $rgb }};
        --bs-link-hover-color: {{ $uLebihGelap }};
        --bs-link-hover-color-rgb: {{ \App\Support\Branding::rgbTeks($uLebihGelap) }};
        --bs-focus-ring-color: {{ \App\Support\Branding::rgba($u, 0.25) }};
    }
    .btn-primary {
        --bs-btn-bg: {{ $u }}; --bs-btn-border-color: {{ $u }};
        --bs-btn-hover-bg: {{ $uGelap }}; --bs-btn-hover-border-color: {{ $uGelap }};
        --bs-btn-active-bg: {{ $uLebihGelap }}; --bs-btn-active-border-color: {{ $uLebihGelap }};
        --bs-btn-disabled-bg: {{ $u }}; --bs-btn-disabled-border-color: {{ $u }};
        --bs-btn-focus-shadow-rgb: {{ $rgb }};
    }
    .btn-outline-primary {
        --bs-btn-color: {{ $u }}; --bs-btn-border-color: {{ $u }};
        --bs-btn-hover-bg: {{ $u }}; --bs-btn-hover-border-color: {{ $u }};
        --bs-btn-active-bg: {{ $uGelap }}; --bs-btn-active-border-color: {{ $uGelap }};
        --bs-btn-disabled-color: {{ $u }}; --bs-btn-disabled-border-color: {{ $u }};
        --bs-btn-focus-shadow-rgb: {{ $rgb }};
    }
    .nav-pills { --bs-nav-pills-link-active-bg: {{ $u }}; }
    .nav { --bs-nav-link-color: {{ $u }}; --bs-nav-link-hover-color: {{ $uLebihGelap }}; }
    .pagination {
        --bs-pagination-color: {{ $u }}; --bs-pagination-hover-color: {{ $uLebihGelap }};
        --bs-pagination-focus-color: {{ $uLebihGelap }};
        --bs-pagination-active-bg: {{ $u }}; --bs-pagination-active-border-color: {{ $u }};
    }
    .dropdown-menu { --bs-dropdown-link-active-bg: {{ $u }}; }
    .list-group { --bs-list-group-active-bg: {{ $u }}; --bs-list-group-active-border-color: {{ $u }}; }
    .progress, .progress-stacked { --bs-progress-bar-bg: {{ $u }}; }
    .form-check-input:checked { background-color: {{ $u }}; border-color: {{ $u }}; }
    .form-control:focus, .form-select:focus, .form-check-input:focus {
        border-color: {{ \App\Support\Branding::rgba($u, 0.5) }};
        box-shadow: 0 0 0 .25rem {{ \App\Support\Branding::rgba($u, 0.25) }};
    }
@endif
</style>
