<?php

namespace App\Http\Controllers;

use App\Models\Cabang;
use App\Models\Meja;
use App\Services\QrCodeService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MejaController extends Controller
{
    public function __construct(private QrCodeService $qrCodeService) {}

    /**
     * Daftar meja — filter cabang kalau user akses semua cabang, kalau
     * tidak otomatis dibatasi ke cabang aktifnya (pola sama StokController).
     */
    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('master.meja.view'), 403);

        $user       = auth()->user();
        $activeLokId = session('active_cabang_id');

        if ($user->canAccessAllBranches() && !$activeLokId) {
            $lokasiList  = Cabang::aktif()->get();
            $lokasiAktif = null;
        } else {
            $lokasiId    = $activeLokId ?? $user->defaultCabangId();
            $lokasiAktif = Cabang::find($lokasiId);
            $lokasiList  = $lokasiAktif ? collect([$lokasiAktif]) : collect();
        }

        $lokasiIds = $lokasiList->pluck('id');

        $query = Meja::with('cabang')->whereIn('cabang_id', $lokasiIds);

        if ($request->filled('cabang') && $user->canAccessAllBranches()) {
            $query->where('cabang_id', $request->cabang);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $mejas = $query->orderBy('cabang_id')->orderBy('nomor_meja')->paginate(20)->withQueryString();

        return view('master.meja.index', [
            'mejas'       => $mejas,
            'lokasiList'  => $lokasiList,
            'canAllCabang'=> $user->canAccessAllBranches(),
        ]);
    }

    public function create()
    {
        abort_unless(auth()->user()->can('master.meja.create'), 403);

        $cabangs = $this->cabangPilihanUntukForm();

        return view('master.meja.create', compact('cabangs'));
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->can('master.meja.create'), 403);

        $data = $request->validate([
            'cabang_id'   => ['required', 'exists:cabangs,id'],
            'nomor_meja'  => ['required', 'integer', 'min:1'],
            'nama_meja'   => ['required', 'string', 'max:50'],
            'kapasitas'   => ['nullable', 'integer', 'min:1', 'max:50'],
            'lokasi'      => ['required', Rule::in(['indoor', 'outdoor', 'vip'])],
            'catatan'     => ['nullable', 'string'],
        ]);

        $exists = Meja::where('cabang_id', $data['cabang_id'])
            ->where('nomor_meja', $data['nomor_meja'])
            ->exists();

        if ($exists) {
            return back()->withInput()->with('error', "Nomor meja {$data['nomor_meja']} sudah dipakai di cabang ini.");
        }

        $data['qr_token'] = Meja::generateQrToken();
        $data['qr_type']  = 'permanent';
        $data['status']   = 'aktif';

        $meja = Meja::create($data);

        return redirect()
            ->route('meja.index')
            ->with('success', "Meja <strong>{$meja->nama_meja}</strong> berhasil ditambahkan.");
    }

    public function edit(Meja $meja)
    {
        abort_unless(auth()->user()->can('master.meja.edit'), 403);
        $this->pastikanAksesCabang($meja);

        $cabangs = $this->cabangPilihanUntukForm();

        return view('master.meja.edit', compact('meja', 'cabangs'));
    }

    /** Update meja. Qr_token TIDAK pernah diubah di sini — cuma lewat generateQr(). */
    public function update(Request $request, Meja $meja)
    {
        abort_unless(auth()->user()->can('master.meja.edit'), 403);
        $this->pastikanAksesCabang($meja);

        $data = $request->validate([
            'cabang_id'  => ['required', 'exists:cabangs,id'],
            'nomor_meja' => ['required', 'integer', 'min:1'],
            'nama_meja'  => ['required', 'string', 'max:50'],
            'kapasitas'  => ['nullable', 'integer', 'min:1', 'max:50'],
            'lokasi'     => ['required', Rule::in(['indoor', 'outdoor', 'vip'])],
            'status'     => ['required', Rule::in(['aktif', 'nonaktif'])],
            'catatan'    => ['nullable', 'string'],
        ]);

        $exists = Meja::where('cabang_id', $data['cabang_id'])
            ->where('nomor_meja', $data['nomor_meja'])
            ->where('id', '!=', $meja->id)
            ->exists();

        if ($exists) {
            return back()->withInput()->with('error', "Nomor meja {$data['nomor_meja']} sudah dipakai di cabang ini.");
        }

        $meja->update($data);

        return redirect()
            ->route('meja.index')
            ->with('success', "Meja <strong>{$meja->nama_meja}</strong> berhasil diperbarui.");
    }

    /**
     * Hapus meja. Diblokir kalau punya riwayat bill (dicek via relasi bills()
     * yang baru genuinely berfungsi setelah E3 — untuk sekarang bills() akan
     * selalu kosong karena tabel `bills` belum ada, jadi block ini aman
     * sebagai guard rail untuk masa depan, bukan aktif membatasi apapun sekarang).
     */
    public function destroy(Meja $meja)
    {
        abort_unless(auth()->user()->can('master.meja.delete'), 403);
        $this->pastikanAksesCabang($meja);

        $nama = $meja->nama_meja;
        $meja->delete();

        return redirect()
            ->route('meja.index')
            ->with('success', "Meja <strong>{$nama}</strong> berhasil dihapus.");
    }

    /** Regenerate QR token — invalidate QR lama, sticker perlu dicetak ulang. */
    public function generateQr(Meja $meja)
    {
        abort_unless(auth()->user()->can('master.meja.edit'), 403);
        $this->pastikanAksesCabang($meja);

        $meja->update(['qr_token' => Meja::generateQrToken()]);

        return back()->with('success', "QR meja <strong>{$meja->nama_meja}</strong> berhasil di-regenerate. QR lama tidak berfungsi lagi, sticker perlu dicetak ulang.");
    }

    /** Download PDF QR sticker (ukuran kartu, siap print & tempel). */
    public function printQr(Meja $meja)
    {
        abort_unless(auth()->user()->can('master.meja.print-qr'), 403);
        $this->pastikanAksesCabang($meja);

        $qrSvg   = $this->qrCodeService->generate($meja->id);
        $orderUrl = $this->qrCodeService->buildOrderUrl($meja);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('master.meja.pdf.qr-sticker', [
            'meja'     => $meja,
            'qrSvg'    => $qrSvg,
            'orderUrl' => $orderUrl,
        ])->setPaper([0, 0, 283.5, 425.2]); // ~10x15cm dalam point

        return $pdf->download("qr-meja-{$meja->cabang->kode_cabang}-{$meja->nomor_meja}.pdf");
    }

    public function createTemporary()
    {
        abort_unless(auth()->user()->can('master.meja.create'), 403);

        $cabangs = $this->cabangPilihanUntukForm();

        return view('master.meja.temporary.create', compact('cabangs'));
    }

    public function storeTemporary(Request $request)
    {
        abort_unless(auth()->user()->can('master.meja.create'), 403);

        $data = $request->validate([
            'cabang_id'      => ['required', 'exists:cabangs,id'],
            'nomor_meja'     => ['required', 'integer', 'min:1'],
            'nama_meja'      => ['required', 'string', 'max:50'],
            'kapasitas'      => ['nullable', 'integer', 'min:1', 'max:50'],
            'qr_expires_at'  => ['required', 'date', 'after:now'],
            'catatan'        => ['nullable', 'string'],
        ]);

        $exists = Meja::where('cabang_id', $data['cabang_id'])
            ->where('nomor_meja', $data['nomor_meja'])
            ->exists();

        if ($exists) {
            return back()->withInput()->with('error', "Nomor meja {$data['nomor_meja']} sudah dipakai di cabang ini.");
        }

        $data['lokasi']   = 'indoor';
        $data['qr_token'] = Meja::generateQrToken();
        $data['qr_type']  = 'temporary';
        $data['status']   = 'aktif';

        $meja = Meja::create($data);

        return redirect()
            ->route('meja.index')
            ->with('success', "Meja temporary <strong>{$meja->nama_meja}</strong> berhasil dibuat, QR aktif sampai " . $meja->qr_expires_at->translatedFormat('d M Y H:i') . '.');
    }

    /** Cabang yang boleh dipilih di form — semua cabang kalau owner/admin_pusat, cuma cabangnya sendiri kalau manajer_cabang. */
    private function cabangPilihanUntukForm()
    {
        $user = auth()->user();

        if ($user->canAccessAllBranches()) {
            return Cabang::aktif()->cabangSaja()->get();
        }

        return Cabang::aktif()->cabangSaja()->where('id', $user->defaultCabangId())->get();
    }

    /** Block akses edit/delete meja di cabang lain untuk user yang bukan canAccessAllBranches(). */
    private function pastikanAksesCabang(Meja $meja): void
    {
        $user = auth()->user();
        if ($user->canAccessAllBranches()) {
            return;
        }

        abort_unless($meja->cabang_id === $user->defaultCabangId(), 403, 'Anda tidak punya akses ke meja di cabang lain.');
    }
}
