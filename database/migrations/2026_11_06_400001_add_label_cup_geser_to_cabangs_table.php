<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Geser posisi label cup per cabang (mm). Printer label + gulungan stiker bisa
 * menggeser isi beberapa mm (foto Owner 2026-11-06: Blueprint ±3,5 mm ke kanan).
 * Diatur sekali di Cabang → Edit → Label Cup, berlaku untuk semua HP/tablet cabang
 * itu; pengaturan di perangkat (localStorage) menambah di atas nilai ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cabangs', function (Blueprint $table) {
            $table->decimal('label_cup_geser_x', 4, 1)->default(0)->after('label_cup_tinggi_mm');
            $table->decimal('label_cup_geser_y', 4, 1)->default(0)->after('label_cup_geser_x');
        });
    }

    public function down(): void
    {
        Schema::table('cabangs', function (Blueprint $table) {
            $table->dropColumn(['label_cup_geser_x', 'label_cup_geser_y']);
        });
    }
};
