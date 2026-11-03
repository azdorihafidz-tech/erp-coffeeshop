<?php

namespace App\Observers;

use App\Models\PengaturanUmum;
use App\Support\Branding;

/**
 * Sprint Identitas Usaha (2026-11-03) — setiap Pengaturan Umum disimpan,
 * cache identitas usaha (App\Support\Branding) dihapus supaya login, sidebar,
 * struk, PDF & PWA langsung memakai nama/logo baru.
 *
 * Dulu observer ini menyalin logo ke public/images/logo.png — file itu ikut
 * git, jadi update program menimpa logo & membuat git pull di server bentrok.
 * Sekarang logo dilayani langsung dari public/uploads/branding (lihat Branding).
 */
class PengaturanUmumObserver
{
    public function saved(PengaturanUmum $setting): void
    {
        app(Branding::class)->lupakan();
    }

    public function deleted(PengaturanUmum $setting): void
    {
        app(Branding::class)->lupakan();
    }
}
