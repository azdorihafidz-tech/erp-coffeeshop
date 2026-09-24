<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('table_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meja_id')->constrained('mejas')->cascadeOnDelete();
            $table->foreignId('bill_id')->nullable()->constrained('bills')->nullOnDelete();
            $table->enum('event_type', [
                'occupied', 'order_added', 'approved', 'transferred',
                'paid', 'vacated', 'kitchen_ready', 'kitchen_complaint',
            ]);
            $table->json('event_data')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['meja_id', 'created_at']);
            $table->index(['bill_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_events');
    }
};
