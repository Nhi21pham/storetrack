<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('source_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('source_invoice_product_id')->nullable()->constrained('invoice_products')->nullOnDelete();
            $table->decimal('unit_cost', 15, 2);
            $table->decimal('quantity_received', 15, 3);
            $table->decimal('quantity_remaining', 15, 3);
            $table->dateTime('received_at');
            $table->timestamps();

            $table->index(['store_id', 'product_id']);
            $table->index(['product_id', 'received_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_batches');
    }
};
