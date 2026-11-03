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
        ], [
            'logo.mimes' => 'Logo harus berupa gambar JPG, PNG, atau WebP.',
            'logo.max'   => 'Ukuran logo maksimal 2 MB.',
        ]);

        $setting = $this->setting();

        $setting->nama_perusahaan   = $validated['nama_perusahaan'];
        $setting->nama_singkat      = $validated['nama_singkat'] ?? null;
        $setting->tagline           = $validated['tagline'] ?? null;
        $setting->alamat_perusahaan = $validated['alamat_perusahaan'] ?? null;
        $setting->telepon           = $validated['telepon'] ?? null;

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
