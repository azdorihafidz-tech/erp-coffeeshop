<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roasting_batches', function (Blueprint $table) {
            $table->enum('green_bean_source', ['in_house', 'bought', 'seed'])->default('seed')->after('green_bean_item_id');
            $table->foreignId('processing_batch_id')->nullable()->after('green_bean_source')->constrained('processing_batches')->nullOnDelete();
            $table->foreignId('pembelian_id')->nullable()->after('processing_batch_id')->constrained('purchase_orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('roasting_batches', function (Blueprint $table) {
            $table->dropForeign(['processing_batch_id']);
            $table->dropForeign(['pembelian_id']);
            $table->dropColumn(['green_bean_source', 'processing_batch_id', 'pembelian_id']);
        });
    }
};
