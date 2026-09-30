<?php

namespace App\Services;

use App\Enums\StatusOrder;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Sprint Analytics Dashboard (2026-10-02) — 5 metrik penjualan agregat
 * (rata-rata nilai/qty transaksi, hari tertinggi nilai/qty, rata-rata omzet
 * harian) dengan filter periode (keseluruhan/tahun berjalan/bulan ini/hari
 * ini). Reusable oleh Dashboard Pusat (semua cabang) & Cabang (per-cabang).
 *
 * Nama sengaja pakai "Sales" (bukan "Dashboard") karena
 * `DashboardAnalyticsService` sudah dipakai untuk widget Kesehatan Finansial
 * (Neraca/LabaRugi/BEP snapshot) — beda scope, hindari bentrok.
 *
 * Scope order: status != Dibatalkan (konsisten dgn widget dashboard existing
 * yang pakai filter yang sama — `Selesai` saja terlalu sempit karena bill
 * yang baru di-Charge sempat status intermediate). Pakai `tanggal_order`
 * (bukan `created_at`) supaya konsisten dgn widget "Omzet Hari Ini" di
 * DashboardController::cabang() / pusat().
 */
class SalesAnalyticsService
{
    public const PERIODE_KESELURUHAN = 'keseluruhan';
    public const PERIODE_TAHUN       = 'tahun';
    public const PERIODE_BULAN       = 'bulan';
    public const PERIODE_HARI        = 'hari';

    public const PERIODE_DEFAULT = self::PERIODE_BULAN;

    public const PERIODE_LABELS = [
        self::PERIODE_KESELURUHAN => 'Keseluruhan',
        self::PERIODE_TAHUN       => 'Tahun Berjalan',
        self::PERIODE_BULAN       => 'Bulan Ini',
        self::PERIODE_HARI        => 'Hari Ini',
    ];

    /**
     * Return semua 5 metrik + info periode dalam 1 array untuk view.
     */
    public function getAllMetrics(?int $cabangId = null, string $periode = self::PERIODE_DEFAULT): array
    {
        $periode = $this->normalisasiPeriode($periode);
        [$start, $end, $days] = $this->getDateRange($periode);

        return [
            'periode'      => $periode,
            'periode_label' => self::PERIODE_LABELS[$periode],
            'start'        => $start,
            'end'          => $end,
            'days'         => $days,
            'avg_tx_value' => $this->getAvgTransactionValue($cabangId, $start, $end),
            'avg_daily_tx' => $this->getAvgDailyTransactions($cabangId, $start, $end, $days),
            'top_value_day' => $this->getHighestValueDay($cabangId, $start, $end),
            'top_qty_day'  => $this->getHighestQtyDay($cabangId, $start, $end),
            'avg_daily_revenue' => $this->getAvgDailyRevenue($cabangId, $start, $end, $days),
        ];
    }

    public function getAvgTransactionValue(?int $cabangId, ?Carbon $start, ?Carbon $end): ?float
    {
        $agg = $this->baseQuery($cabangId, $start, $end)
            ->selectRaw('COUNT(*) as jml, COALESCE(SUM(total_bayar), 0) as omzet')
            ->first();
        if (!$agg || (int) $agg->jml === 0) {
            return null;
        }
        return round(((float) $agg->omzet) / (int) $agg->jml, 2);
    }

    public function getAvgDailyTransactions(?int $cabangId, ?Carbon $start, ?Carbon $end, ?int $days): ?float
    {
        // Untuk periode "keseluruhan", days-nya dihitung dari tx pertama vs
        // hari ini — bukan angka statik — supaya tidak kacau kalau baru
        // beberapa hari data (mis. sistem baru live 1 minggu).
        $daysEffective = $this->effectiveDaysForAverage($cabangId, $start, $end, $days);
        if (!$daysEffective) {
            return null;
        }
        $count = (int) $this->baseQuery($cabangId, $start, $end)->count();
        if ($count === 0) {
            return null;
        }
        return round($count / $daysEffective, 2);
    }

    public function getAvgDailyRevenue(?int $cabangId, ?Carbon $start, ?Carbon $end, ?int $days): ?float
    {
        $daysEffective = $this->effectiveDaysForAverage($cabangId, $start, $end, $days);
        if (!$daysEffective) {
            return null;
        }
        $omzet = (float) $this->baseQuery($cabangId, $start, $end)->sum('total_bayar');
        if ($omzet <= 0) {
            return null;
        }
        return round($omzet / $daysEffective, 2);
    }

    /** Return ['tanggal' => Carbon, 'omzet' => float, 'jumlah' => int] atau null. */
    public function getHighestValueDay(?int $cabangId, ?Carbon $start, ?Carbon $end): ?array
    {
        $row = $this->baseQuery($cabangId, $start, $end)
            ->selectRaw('DATE(tanggal_order) as tgl, COUNT(*) as jml, COALESCE(SUM(total_bayar), 0) as omzet')
            ->groupBy('tgl')
            ->orderByDesc('omzet')
            ->limit(1)
            ->first();
        return $row ? [
            'tanggal' => Carbon::parse($row->tgl),
            'omzet'   => (float) $row->omzet,
            'jumlah'  => (int) $row->jml,
        ] : null;
    }

    public function getHighestQtyDay(?int $cabangId, ?Carbon $start, ?Carbon $end): ?array
    {
        $row = $this->baseQuery($cabangId, $start, $end)
            ->selectRaw('DATE(tanggal_order) as tgl, COUNT(*) as jml, COALESCE(SUM(total_bayar), 0) as omzet')
            ->groupBy('tgl')
            ->orderByDesc('jml')
            ->limit(1)
            ->first();
        return $row ? [
            'tanggal' => Carbon::parse($row->tgl),
            'omzet'   => (float) $row->omzet,
            'jumlah'  => (int) $row->jml,
        ] : null;
    }

    // ===== Internal =====

    private function normalisasiPeriode(string $periode): string
    {
        return array_key_exists($periode, self::PERIODE_LABELS) ? $periode : self::PERIODE_DEFAULT;
    }

    /**
     * @return array{0: ?Carbon, 1: ?Carbon, 2: ?int} [start, end, days_count]
     *         (start/end null = tanpa batas untuk periode 'keseluruhan')
     */
    public function getDateRange(string $periode): array
    {
        $now = Carbon::now();
        return match ($periode) {
            self::PERIODE_HARI => [$now->copy()->startOfDay(), $now->copy()->endOfDay(), 1],
            self::PERIODE_BULAN => [$now->copy()->startOfMonth(), $now->copy()->endOfDay(), $now->day],
            self::PERIODE_TAHUN => [$now->copy()->startOfYear(), $now->copy()->endOfDay(), $now->dayOfYear],
            default => [null, null, null], // keseluruhan
        };
    }

    private function baseQuery(?int $cabangId, ?Carbon $start, ?Carbon $end): Builder
    {
        $q = Order::withoutGlobalScopes()
            ->where('status', '!=', StatusOrder::Dibatalkan);
        if ($cabangId) {
            $q->where('cabang_id', $cabangId);
        }
        if ($start) {
            $q->where('tanggal_order', '>=', $start);
        }
        if ($end) {
            $q->where('tanggal_order', '<=', $end);
        }
        return $q;
    }

    /**
     * Days effective untuk rata-rata harian. Untuk periode dengan range
     * spesifik: pakai $days apa adanya. Untuk 'keseluruhan': hitung dari tx
     * pertama sampai hari ini (min 1) — hindari average dibagi angka gede
     * kalau ternyata data baru sedikit.
     */
    private function effectiveDaysForAverage(?int $cabangId, ?Carbon $start, ?Carbon $end, ?int $days): ?int
    {
        if ($days !== null) {
            return max(1, $days);
        }

        $firstDate = $this->baseQuery($cabangId, null, null)->min(DB::raw('DATE(tanggal_order)'));
        if (!$firstDate) {
            return null;
        }
        $first = Carbon::parse($firstDate);
        $diff = $first->diffInDays(Carbon::today()) + 1;
        return max(1, (int) $diff);
    }
}
