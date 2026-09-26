<?php

namespace Database\Seeders;

use App\Models\Cabang;
use App\Models\Item;
use App\Models\ProcessingBatch;
use App\Models\User;
use App\Services\StokService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * Roastery V2 Minggu 3-4 (2026-09-28) — 1 contoh batch processing SELESAI
 * (Washed Arabika) untuk demo data. Stok green bean ditambah via
 * StokService::masuk() existing (FIFO batch + movement, CLAUDE.md 3.3),
 * idempotent lewat cek kode_batch dulu supaya reseed tidak dobel-tambah stok.
 *
 * CATATAN: instruksi awal minta "10kg cherry -> 8kg green" (yield 80%),
 * tapi itu keliru untuk cherry->green (cherry basah jauh lebih berat dari
 * green bean-nya, yield realistis cherry->green umumnya ~15-20%, BUKAN
 * 80-85% yang berlaku utk green->roasted). Contoh ini dipakai dengan angka
 * yang benar: 10 kg cherry -> 1,8 kg green (yield 18%) supaya tidak
 * menyesatkan Owner soal ekspektasi hasil processing riil.
 */
class ProcessingBatchSeeder extends Seeder
{
    public function run(): void
    {
        $kode = 'PB-' . now()->format('Ym') . '-0001';

        if (ProcessingBatch::where('kode_batch', $kode)->exists()) {
            $this->command?->info('ProcessingBatchSeeder: sudah ada, di-skip.');
            return;
        }

        $rst = Cabang::where('kode_cabang', 'RST001')->value('id');
        $owner = User::where('email', 'admin@kopidrip.com')->value('id');
        $cherry = Item::where('kode_item', 'BHK-ARABIKA-SDK')->first();
        $green  = Item::where('kode_item', 'GRB-ARB-WASHED')->first();

        if (! $rst || ! $owner || ! $cherry || ! $green) {
            $this->command?->warn('ProcessingBatchSeeder: data prasyarat belum lengkap, di-skip.');
            return;
        }

        $ownerUser = User::find($owner);
        if ($ownerUser) {
            Auth::login($ownerUser);
        }

        $cherryQty = 10.0;
        $costAwal  = $cherryQty * 12000; // konsisten dgn harga contoh CherryPurchaseSeeder
        $greenQty  = 1.8; // yield realistis cherry->green ~18%
        $defectQty = 0.3;
        $costPerKg = round($costAwal / $greenQty, 2);

        ProcessingBatch::create([
            'kode_batch'         => $kode,
            'tanggal_mulai'      => now()->subDays(10)->toDateString(),
            'tanggal_selesai'    => now()->toDateString(),
            'cabang_id'          => $rst,
            'user_id'            => $owner,
            'cherry_item_id'     => $cherry->id,
            'cherry_qty_kg'      => $cherryQty,
            'cherry_cost_awal'   => $costAwal,
            'processing_method'  => 'washed',
            'drying_start'       => now()->subDays(9),
            'drying_end'         => now()->subDays(4),
            'drying_catatan'     => 'Kadar air target ~11% tercapai',
            'hulling_qty_kg'     => 2.1,
            'sortir_qty_kg'      => $greenQty,
            'sortir_defect_kg'   => $defectQty,
            'green_bean_item_id' => $green->id,
            'status'             => 'selesai',
            'yield_percent'      => round($greenQty / $cherryQty * 100, 2),
            'cost_per_kg_green'  => $costPerKg,
            'catatan_umum'       => 'Contoh data demo — batch Washed Arabika selesai',
        ]);

        $green->update(['harga_beli_terakhir' => $costPerKg]);

        // Reuse StokService::masuk() existing (FIFO batch + movement) — CLAUDE.md 3.3.
        app(StokService::class)->masuk(
            $green->id, $rst, $greenQty,
            "Hasil processing {$kode} (Washed) — data demo seeder",
            'processing_batch', 0,
            $costPerKg
        );

        $this->command?->info("ProcessingBatchSeeder: 1 batch demo selesai ({$kode}), yield ".round($greenQty / $cherryQty * 100, 2)."%, stok green {$greenQty} kg di-seed.");
    }
}
