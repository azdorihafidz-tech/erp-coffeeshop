<?php

namespace App\Services;

use App\Exceptions\PeriodeTerkunciException;
use App\Models\ClosingPeriode;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint Closing Periode (2026-10-17) — penjaga periode terkunci.
 *
 * Closing Final per cabang mengunci cabang itu; closing Final Konsolidasi
 * (cabang_id NULL) mengunci SEMUA cabang di rentang tanggalnya. Daftar
 * closing final dimuat sekali per request (di-singleton di
 * AppServiceProvider) — selama belum ada closing final sama sekali, cek ini
 * tidak menambah query apa pun ke alur existing.
 */
class ClosingLockService
{
    /** @var array<int, array{kode:string,cabang_id:?int,mulai:string,selesai:string}>|null */
    private ?array $locks = null;

    public function flush(): void
    {
        $this->locks = null;
    }

    public function adaKunci(): bool
    {
        return count($this->locks()) > 0;
    }

    /**
     * Closing final yang mengunci (cabang, tanggal) ini, atau null.
     */
    public function kunciUntuk(?int $cabangId, $tanggal): ?array
    {
        if ($tanggal === null || !$this->adaKunci()) {
            return null;
        }

        $tgl = $tanggal instanceof \DateTimeInterface
            ? Carbon::instance($tanggal)->toDateString()
            : Carbon::parse($tanggal)->toDateString();

        foreach ($this->locks() as $lock) {
            $cocokCabang = $lock['cabang_id'] === null || ($cabangId !== null && $lock['cabang_id'] === $cabangId);
            if ($cocokCabang && $tgl >= $lock['mulai'] && $tgl <= $lock['selesai']) {
                return $lock;
            }
        }

        return null;
    }

    public function assertBolehUbah(?int $cabangId, $tanggal, string $konteks = 'data'): void
    {
        $lock = $this->kunciUntuk($cabangId, $tanggal);
        if (!$lock) {
            return;
        }

        $tgl = Carbon::parse($tanggal instanceof \DateTimeInterface ? $tanggal->format('Y-m-d') : $tanggal)
            ->translatedFormat('d M Y');

        throw new PeriodeTerkunciException(
            "Tidak bisa mengubah {$konteks} tanggal {$tgl}: periode ini sudah di-closing ({$lock['kode']}). "
            . 'Minta Owner membuka kunci closing tersebut di menu Keuangan → Closing Periode bila memang perlu koreksi.'
        );
    }

    private function locks(): array
    {
        if ($this->locks !== null) {
            return $this->locks;
        }

        try {
            if (!Schema::hasTable('closing_periodes')) {
                return $this->locks = [];
            }

            $this->locks = ClosingPeriode::query()
                ->where('status', ClosingPeriode::STATUS_FINAL)
                ->get(['kode', 'cabang_id', 'tanggal_mulai', 'tanggal_selesai'])
                ->map(fn ($c) => [
                    'kode'      => $c->kode,
                    'cabang_id' => $c->cabang_id !== null ? (int) $c->cabang_id : null,
                    'mulai'     => $c->tanggal_mulai->toDateString(),
                    'selesai'   => $c->tanggal_selesai->toDateString(),
                ])
                ->all();
        } catch (\Throwable) {
            // Jangan pernah memblok transaksi karena tabel closing bermasalah.
            $this->locks = [];
        }

        return $this->locks;
    }
}
