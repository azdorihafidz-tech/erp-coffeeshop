<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** E3 — lengkapi stub `bill_items` dari E2 dengan field workflow dapur & sortir. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bill_items', function (Blueprint $table) {
            $table->timestamp('status_dapur_at')->nullable()->after('status_dapur');
            $table->foreignId('status_dapur_by')->nullable()->after('status_dapur_at')->constrained('users')->nullOnDelete();
            $table->string('komplain_reason', 255)->nullable()->after('status_dapur_by');
            $table->integer('urutan_masuk')->default(0)->after('komplain_reason');
        });
    }

    public function down(): void
    {
        Schema::table('bill_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('status_dapur_by');
            $table->dropColumn(['status_dapur_at', 'komplain_reason', 'urutan_masuk']);
        });
    }
};
