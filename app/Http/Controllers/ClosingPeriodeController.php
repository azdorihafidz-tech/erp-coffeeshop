<?php

namespace App\Http\Controllers;

use App\Exports\ClosingPeriodeExport;
use App\Models\Cabang;
use App\Models\ClosingPeriode;
use App\Services\ClosingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Sprint Closing Periode (2026-10-17) — closing harian/mingguan/bulanan/
 * custom per cabang atau konsolidasi, dengan snapshot permanen & kunci periode.
 */
class ClosingPeriodeController extends Controller
{
    public function __construct(private ClosingService $service)
    {
    }

    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('closing.view'), 403);
        $user = auth()->user();

        $query = ClosingPeriode::with(['cabang', 'dibuatOleh', 'difinalkanOleh'])
            ->orderByDesc('tanggal_selesai')->orderByDesc('id');

        if ($user->canAccessAllBranches()) {
            if ($request->cabang_id === 'konsolidasi') {
                $query->whereNull('cabang_id');
            } elseif ($request->filled('cabang_id')) {
                $query->where('cabang_id', (int) $request->cabang_id);
            }
        } else {
            $query->where('cabang_id', $this->cabangAktifId());
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('jenis')) {
            $query->where('jenis', $request->jenis);
        }

        $closings = $query->paginate(20)->withQueryString();

        return view('closing.index', [
            'closings'    => $closings,
            'cabangs'     => $user->canAccessAllBranches() ? Cabang::aktif()->orderBy('nama_cabang')->get() : collect(),
            'multiCabang' => Cabang::aktif()->count() > 1,
        ]);
    }

    public function create(Request $request)
    {
        abort_unless(auth()->user()->can('closing.create'), 403);

        return view('closing.create', [
            'cakupanOptions' => $this->cakupanOptions(),
            'default'        => [
                'jenis'          => $request->get('jenis', 'harian'),
                'tanggal_mulai'  => $request->get('tanggal_mulai', today()->toDateString()),
                'tanggal_selesai'=> $request->get('tanggal_selesai', today()->toDateString()),
                'cakupan'        => $request->get('cakupan'),
            ],
        ]);
    }

    public function preview(Request $request)
    {
        abort_unless(auth()->user()->can('closing.create'), 403);
        [$jenis, $cabangId, $mulai, $selesai] = $this->inputPeriode($request);

        try {
            $this->service->validasiPeriode($jenis, $cabangId, $mulai, $selesai);
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('closing.create', $request->only('jenis', 'tanggal_mulai', 'tanggal_selesai', 'cakupan'))
                ->with('error', $e->getMessage());
        }

        $data = $this->service->hitung($cabangId, $mulai, $selesai);
        $bentrok = $this->service->bentrokFinal($jenis, $cabangId, $mulai, $selesai);

        return view('closing.preview', [
            'data'     => $data,
            'jenis'    => $jenis,
            'cabangId' => $cabangId,
            'cakupan'  => $request->cakupan,
            'mulai'    => $mulai,
            'selesai'  => $selesai,
            'bentrok'  => $bentrok,
            'namaCakupan' => $cabangId ? Cabang::find($cabangId)?->nama_cabang : 'Konsolidasi (Semua Cabang)',
        ]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        abort_unless($user->can('closing.create'), 403);
        $request->validate([
            'aksi'    => 'required|in:draft,final',
            'catatan' => 'nullable|string|max:1000',
        ]);
        [$jenis, $cabangId, $mulai, $selesai] = $this->inputPeriode($request);

        $final = $request->aksi === 'final';
        abort_if($final && !$user->can('closing.finalize'), 403);

        try {
            $closing = $this->service->simpan($jenis, $cabangId, $mulai, $selesai, $user, $final, $request->catatan);
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('closing.show', $closing)->with('success', $final
            ? "Closing {$closing->kode} berhasil dikunci. Data periode ini sekarang tersimpan permanen."
            : "Draft closing {$closing->kode} tersimpan. Minta Owner/Admin Pusat mengunci (Final) bila angka sudah benar.");
    }

    public function show(Request $request, ClosingPeriode $closing)
    {
        abort_unless(auth()->user()->can('closing.view'), 403);
        $this->authorizeCakupan($closing);
        $closing->load(['cabang', 'dibuatOleh', 'difinalkanOleh', 'dibukaOleh', 'parent']);

        $perbandingan = null;
        if ($request->boolean('bandingkan')) {
            $live = $this->service->hitung($closing->cabang_id, $closing->tanggal_mulai, $closing->tanggal_selesai);
            $perbandingan = $this->bandingkan($closing->data ?? [], $live);
        }

        return view('closing.show', [
            'closing'      => $closing,
            'data'         => $closing->data ?? [],
            'perbandingan' => $perbandingan,
            'versiLain'    => ClosingPeriode::where('id', '!=', $closing->id)
                ->where('jenis', $closing->jenis)
                ->when($closing->cabang_id === null, fn ($q) => $q->whereNull('cabang_id'), fn ($q) => $q->where('cabang_id', $closing->cabang_id))
                ->whereDate('tanggal_mulai', $closing->tanggal_mulai)
                ->whereDate('tanggal_selesai', $closing->tanggal_selesai)
                ->orderByDesc('versi')->get(),
        ]);
    }

    public function finalize(ClosingPeriode $closing)
    {
        $user = auth()->user();
        abort_unless($user->can('closing.finalize'), 403);
        $this->authorizeCakupan($closing);

        try {
            $this->service->finalkan($closing, $user);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('closing.show', $closing)->with('success', "Closing {$closing->kode} berhasil dikunci (Final).");
    }

    public function unlock(Request $request, ClosingPeriode $closing)
    {
        $user = auth()->user();
        abort_unless($user->can('closing.unlock'), 403);
        $this->authorizeCakupan($closing);
        $request->validate(['alasan_buka' => 'required|string|min:5|max:1000'], [
            'alasan_buka.required' => 'Alasan buka kunci wajib diisi.',
            'alasan_buka.min'      => 'Alasan buka kunci minimal 5 karakter.',
        ]);

        try {
            $this->service->bukaKunci($closing, $user, $request->alasan_buka);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('closing.show', $closing)->with('success',
            "Kunci closing {$closing->kode} dibuka. Data periode ini bisa dikoreksi; lakukan closing ulang setelah selesai (arsip lama tetap tersimpan).");
    }

    public function destroy(ClosingPeriode $closing)
    {
        abort_unless(auth()->user()->can('closing.create'), 403);
        $this->authorizeCakupan($closing);

        if ($closing->status !== ClosingPeriode::STATUS_DRAFT) {
            return back()->with('error', 'Hanya closing Draft yang bisa dihapus. Closing Final/Dibuka disimpan sebagai arsip.');
        }
        $kode = $closing->kode;
        $closing->delete();

        return redirect()->route('closing.index')->with('success', "Draft closing {$kode} dihapus.");
    }

    public function exportPdf(ClosingPeriode $closing)
    {
        abort_unless(auth()->user()->can('closing.export'), 403);
        $this->authorizeCakupan($closing);
        $closing->load(['cabang', 'difinalkanOleh']);

        $pdf = Pdf::loadView('closing.pdf', [
            'closing'      => $closing,
            'data'         => $closing->data ?? [],
            'judulLaporan' => 'Laporan Closing ' . $closing->labelJenis(),
            'filterInfo'   => [
                'Kode'    => $closing->kode . ' (v' . $closing->versi . ')',
                'Periode' => $closing->labelPeriode(),
                'Cakupan' => $closing->namaCakupan(),
                'Status'  => ucfirst($closing->status),
            ],
            'footerDicetak' => 'Dicetak oleh: ' . auth()->user()->name . ' pada ' . now()->translatedFormat('d F Y, H:i') . ' WIB',
        ])->setPaper('a4', 'portrait');

        return $pdf->download('Closing-' . $closing->kode . '.pdf');
    }

    public function exportExcel(ClosingPeriode $closing)
    {
        abort_unless(auth()->user()->can('closing.export'), 403);
        $this->authorizeCakupan($closing);
        $closing->load('cabang');

        return Excel::download(new ClosingPeriodeExport($closing, auth()->user()->name), 'Closing-' . $closing->kode . '.xlsx');
    }

    // =====================================================================

    private function cabangAktifId(): ?int
    {
        $user = auth()->user();
        $id = session('active_cabang_id') ?? $user->defaultCabangId();

        return $id ? (int) $id : null;
    }

    /** Pilihan cakupan sesuai hak akses: [value => label]. */
    private function cakupanOptions(): array
    {
        $user = auth()->user();
        $cabangs = Cabang::aktif()->orderBy('nama_cabang')->get();

        if (!$user->canAccessAllBranches()) {
            $id = $this->cabangAktifId();
            $c = $cabangs->firstWhere('id', $id);

            return $c ? [(string) $c->id => $c->nama_cabang] : [];
        }

        $opsi = [];
        if ($cabangs->count() > 1 && $user->can('closing.konsolidasi')) {
            $opsi['konsolidasi'] = 'Konsolidasi (Semua Cabang)';
        }
        foreach ($cabangs as $c) {
            $opsi[(string) $c->id] = $c->nama_cabang;
        }

        return $opsi;
    }

    /** @return array{0:string,1:?int,2:Carbon,3:Carbon} */
    private function inputPeriode(Request $request): array
    {
        $request->validate([
            'jenis'           => 'required|in:' . implode(',', array_keys(ClosingPeriode::JENIS)),
            'tanggal_mulai'   => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
            'cakupan'         => 'required|string',
        ], [
            'tanggal_selesai.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
            'cakupan.required'               => 'Pilih cakupan closing (cabang atau konsolidasi).',
        ]);

        $opsi = $this->cakupanOptions();
        abort_unless(array_key_exists((string) $request->cakupan, $opsi), 403, 'Anda tidak punya akses ke cakupan closing ini.');

        $cabangId = $request->cakupan === 'konsolidasi' ? null : (int) $request->cakupan;

        return [
            $request->jenis,
            $cabangId,
            Carbon::parse($request->tanggal_mulai)->startOfDay(),
            Carbon::parse($request->tanggal_selesai)->startOfDay(),
        ];
    }

    private function authorizeCakupan(ClosingPeriode $closing): void
    {
        $user = auth()->user();
        if ($user->canAccessAllBranches()) {
            return;
        }
        abort_if($closing->cabang_id === null || $closing->cabang_id !== $this->cabangAktifId(), 403);
    }

    /** Selisih angka utama antara snapshot closing dan data saat ini. */
    private function bandingkan(array $snap, array $live): array
    {
        $baris = [
            'Jumlah Transaksi'    => ['penjualan', 'jumlah_transaksi'],
            'Omzet'               => ['laba_rugi', 'omzet'],
            'HPP'                 => ['laba_rugi', 'hpp'],
            'Laba Kotor'          => ['laba_rugi', 'laba_kotor'],
            'Pemasukan Lain'      => ['laba_rugi', 'pemasukan_lain'],
            'Beban Operasional'   => ['laba_rugi', 'beban'],
            'Laba Bersih'         => ['laba_rugi', 'laba_bersih'],
            'Total Kas Akhir'     => ['kas', 'total_akhir'],
            'Dalam Perjalanan'    => ['keuangan', 'dalam_perjalanan'],
            'Nilai Stok Akhir'    => ['stok', 'total_nilai'],
        ];

        $hasil = [];
        foreach ($baris as $label => [$a, $b]) {
            $s = (float) ($snap[$a][$b] ?? 0);
            $l = (float) ($live[$a][$b] ?? 0);
            $hasil[] = ['label' => $label, 'snapshot' => $s, 'sekarang' => $l, 'selisih' => round($l - $s, 2)];
        }

        return $hasil;
    }
}
