<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** E3 — lengkapi stub `bills` dari E2 dengan kolom penuh (diskon/pajak, transfer, payment mode). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->decimal('diskon', 15, 2)->default(0)->after('sub_total');
            $table->decimal('pajak', 15, 2)->default(0)->after('diskon');
            $table->text('catatan')->nullable()->after('total');
            $table->foreignId('closed_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->foreignId('transferred_from_meja_id')->nullable()->after('closed_by')->constrained('mejas')->nullOnDelete();
            $table->timestamp('transferred_at')->nullable()->after('transferred_from_meja_id');
            $table->string('customer_name', 100)->nullable()->after('transferred_at');
            $table->string('customer_phone', 20)->nullable()->after('customer_name');
            $table->enum('payment_mode', ['bayar_dulu', 'bayar_di_kasir'])->default('bayar_di_kasir')->after('customer_phone');
        });
    }

    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->dropConstrainedForeignId('closed_by');
            $table->dropConstrainedForeignId('transferred_from_meja_id');
            $table->dropColumn([
                'diskon', 'pajak', 'catatan', 'transferred_at',
                'customer_name', 'customer_phone', 'payment_mode',
            ]);
        });
    }
};
