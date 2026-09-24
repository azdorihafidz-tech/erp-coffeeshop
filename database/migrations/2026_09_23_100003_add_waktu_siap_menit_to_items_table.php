<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->unsignedInteger('waktu_siap_menit')
                ->default(0)
                ->nullable()
                ->after('harga_beli_terakhir')
                ->comment('Estimasi waktu bikin per unit dalam menit');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('waktu_siap_menit');
        });
    }
};
