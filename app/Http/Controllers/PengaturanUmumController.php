<?php

namespace App\Http\Controllers;

use App\Models\PengaturanUmum;
use App\Support\Branding;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PengaturanUmumController extends Controller
{
    public function edit()
    {
        abort_unless(auth()->user()->can('pengaturan.umum_lihat'), 403);

        $setting = $this->setting();

        return view('pengaturan.umum', compact('setting'));
    }

    public function update(Request $request, Branding $branding)
    {
        abort_unless(auth()->user()->can('pengaturan.umum_edit'), 403);

        // Sprint Identitas Usaha — SVG tidak diterima (bisa berisi script & tidak
        // bisa diolah jadi ikon). Logo selalu disimpan ulang sebagai PNG.
        $validated = $request->validate([
            'nama_perusahaan'   => ['required', 'string', 'max:100'],
            'nama_singkat'      => ['nullable', 'string', 'max:30'],
            'tagline'           => ['nullable', 'string', 'max:150'],
            'alamat_perusahaan' => ['nullable', 'string', 'max:1000'],
            'telepon'           => ['nullable', 'string', 'max:30'],
            'logo'              => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            // 4.54 Warna Tampilan — kosong = warna bawaan program
            'warna_utama'       => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'warna_sidebar'     => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'warna_latar'       => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ], [
            'logo.mimes' => 'Logo harus berupa gambar JPG, PNG, atau WebP.',
            'logo.max'   => 'Ukuran logo maksimal 2 MB.',
            'warna_utama.regex'   => 'Kode warna utama harus seperti #2D6A4F.',
            'warna_sidebar.regex' => 'Kode warna sidebar harus seperti #1A1A1A.',
            'warna_latar.regex'   => 'Kode warna latar harus seperti #F5F0E6.',
        ]);

        // Warna: "Kembalikan ke bawaan" mengosongkan ketiganya; selain itu wajib lolos aturan keterbacaan
        $warna = [];
        foreach (['utama', 'sidebar', 'latar'] as $k) {
            $v = $request->boolean('reset_warna') ? null : ($validated['warna_' . $k] ?? null);
            $warna[$k] = $v ? strtoupper($v) : null;
        }
        $masalah = Branding::cekKeterbacaan(
            $warna['utama'] ?? Branding::WARNA_BAWAAN['utama'],
            $warna['sidebar'] ?? Branding::WARNA_BAWAAN['sidebar'],
            $warna['latar'] ?? Branding::WARNA_BAWAAN['latar'],
        );
        if ($masalah) {
            return back()->withInput()->withErrors($masalah);
        }

        $setting = $this->setting();
        // Kolom warna belum ada kalau Update Database belum dijalankan → lewati, data lain tetap tersimpan
        if (\Illuminate\Support\Facades\Schema::hasColumn('pengaturan_umums', 'warna_utama')) {
            $setting->warna_utama   = $warna['utama'];
            $setting->warna_sidebar = $warna['sidebar'];
            $setting->warna_latar   = $warna['latar'];
        }

        $setting->nama_perusahaan   = $validated['nama_perusahaan'];
        $setting->nama_singkat      = $validated['nama_singkat'] ?? null;
        $setting->tagline           = $validated['tagline'] ?? null;
        $setting->alamat_perusahaan = $validated['alamat_perusahaan'] ?? null;
        $setting->telepon           = $validated['telepon'] ?? null;

        // Stok Opname — hitung buta, batas foto barang hilang, pengingat akhir bulan
        $opnameLama = null;
        if ($request->has('opname_form') && \Illuminate\Support\Facades\Schema::hasColumn('pengaturan_umums', 'opname_hitung_buta')) {
            $o = $request->validate([
                'opname_batas_foto_hilang' => ['required', 'numeric', 'min:0', 'max:999999999'],
                'opname_pengingat_hari'    => ['required', 'integer', 'min:1', 'max:10'],
                'opname_pengingat_jam'     => ['required', 'date_format:H:i'],
            ], [
                'opname_pengingat_hari.min' => 'Pengingat paling cepat H-1.', 'opname_pengingat_hari.max' => 'Pengingat paling lama H-10.',
                'opname_pengingat_jam.date_format' => 'Jam pengingat tidak valid (contoh 08:00).',
            ]);
            $opnameLama = $setting->only(['opname_hitung_buta', 'opname_batas_foto_hilang', 'opname_pengingat_aktif', 'opname_pengingat_hari', 'opname_pengingat_jam']);
            $setting->opname_hitung_buta       = $request->boolean('opname_hitung_buta');
            $setting->opname_batas_foto_hilang = (float) $o['opname_batas_foto_hilang'];
            $setting->opname_pengingat_aktif   = $request->boolean('opname_pengingat_aktif');
            $setting->opname_pengingat_hari    = (int) $o['opname_pengingat_hari'];
            $setting->opname_pengingat_jam     = $o['opname_pengingat_jam'];
        }

        if ($request->hasFile('logo') && $request->file('logo')->isValid()) {
            try {
                $baru = $branding->simpanLogo($request->file('logo'));
            } catch (\Throwable $e) {
                Log::warning('Gagal memproses logo: ' . $e->getMessage());

                return back()->withInput()->withErrors(['logo' => 'Logo gagal diproses. Coba file PNG/JPG lain.']);
            }
            $this->hapusLogoLamaDisk($setting->logo_path);
            $setting->logo_path = $baru;
        } elseif ($request->boolean('hapus_logo') && $setting->logo_path) {
            $branding->hapusFileLogo();
            $this->hapusLogoLamaDisk($setting->logo_path);
            $setting->logo_path = null;
        }

        $setting->save();
        $branding->lupakan();

        activity()
            ->performedOn($setting)
            ->log('Update pengaturan umum perusahaan');

        if ($opnameLama !== null) {
            $opnameBaru = $setting->only(array_keys($opnameLama));
            if ($opnameBaru != $opnameLama) {
                activity('Pengaturan')->performedOn($setting)->causedBy(auth()->user())
                    ->withProperties(['old' => $opnameLama, 'attributes' => $opnameBaru])
                    ->log('Ubah pengaturan Stok Opname');
            }
        }

        return redirect()
            ->route('pengaturan.umum')
            ->with('success', 'Pengaturan umum berhasil disimpan.');
    }

    private function setting(): PengaturanUmum
    {
        return PengaturanUmum::firstOrCreate([], ['nama_perusahaan' => 'Kopi Drip Sidikalang']);
    }

    /** Logo yang di-upload sebelum sprint Identitas Usaha tersimpan di disk public. */
    private function hapusLogoLamaDisk(?string $path): void
    {
        if ($path && !str_starts_with($path, Branding::FOLDER . '/') && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
