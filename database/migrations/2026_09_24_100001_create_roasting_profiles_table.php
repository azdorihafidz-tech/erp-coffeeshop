<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roasting_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 50);
            $table->string('slug', 60)->unique();
            $table->enum('level_target', ['light', 'medium', 'dark', 'custom'])->default('custom');
            $table->decimal('avg_susut_percent', 5, 2)->default(15);
            $table->unsignedSmallInteger('suhu_target_celsius')->nullable();
            $table->unsignedSmallInteger('waktu_target_menit')->nullable();
            $table->text('catatan')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roasting_profiles');
    }
};
