<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('petani', function (Blueprint $table) {
            $table->id();
            $table->string('kode_petani', 20)->unique();
            $table->string('nama', 150);
            $table->string('telepon', 20)->nullable();
            $table->text('alamat')->nullable();
            $table->string('nama_kebun', 150)->nullable();
            $table->decimal('koordinat_lat', 10, 7)->nullable();
            $table->decimal('koordinat_lng', 10, 7)->nullable();
            $table->text('catatan')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('petani');
    }
};
