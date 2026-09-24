<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kitchen_display_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabang_id')->unique()->constrained('cabangs')->cascadeOnDelete();
            $table->boolean('is_active')->default(false);
            $table->integer('auto_refresh_seconds')->default(10);
            $table->string('alert_sound_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kitchen_display_settings');
    }
};
