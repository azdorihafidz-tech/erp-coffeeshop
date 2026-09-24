<?php

namespace App\Http\Controllers;

use App\Models\RoastingBatch;
use App\Services\RoastingBatchService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class RoasteryDashboardController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('roastery.dashboard.view'), 403);

        $bulan = $request->filled('bulan') ? Carbon::parse($request->bulan . '-01') : now()->startOfMonth();
        $gp = RoastingBatchService::roasteryCabangId();

        $base = fn () => RoastingBatch::where('cabang_id', $gp)->where('status', 'completed');

        $bulanIni = $base()->whereBetween('tanggal', [$bulan->copy()->startOfMonth(), $bulan->copy()->endOfMonth()])->get();

        $totalGreen  = (float) $bulanIni->sum('green_qty_kg');
        $totalRoast  = (float) $bulanIni->sum('roasted_qty_kg');
        $ringkasan = [
            'jumlah_batch'   => $bulanIni->count(),
            'total_green'    => $totalGreen,
            'total_roasted'  => $totalRoast,
            'total_waste'    => (float) $bulanIni->sum('waste_qty_kg'),
            'avg_yield'      => $totalGreen > 0 ? round($totalRoast / $totalGreen * 100, 2) : 0,
            'avg_cost_per_kg'=> $totalRoast > 0 ? round((float) $bulanIni->sum('cost_awal') / $totalRoast, 0) : 0,
        ];

        // Tren 30 hari terakhir (per tanggal) — yield tertimbang & waste
        $dari = now()->subDays(29)->startOfDay();
        $tren = $base()->where('tanggal', '>=', $dari)->get()->groupBy(fn ($b) => $b->tanggal->format('Y-m-d'));
        $labels = $yield = $waste = [];
        for ($d = $dari->copy(); $d->lte(now()); $d->addDay()) {
            $key = $d->format('Y-m-d');
            $rows = $tren->get($key, collect());
            $g = (float) $rows->sum('green_qty_kg');
            $labels[] = $d->format('d/m');
            $yield[]  = $g > 0 ? round((float) $rows->sum('roasted_qty_kg') / $g * 100, 2) : null;
            $waste[]  = round((float) $rows->sum('waste_qty_kg'), 3);
        }

        $terbaru = RoastingBatch::with(['greenBean', 'profile'])
            ->where('cabang_id', $gp)->orderByDesc('tanggal')->orderByDesc('id')->limit(5)->get();

        return view('roastery.dashboard.index', compact('bulan', 'ringkasan', 'labels', 'yield', 'waste', 'terbaru'));
    }
}
