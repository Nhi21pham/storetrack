<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_product_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_product_id')->constrained('invoice_products')->cascadeOnDelete();
            $table->foreignId('inventory_batch_id')->constrained('inventory_batches')->restrictOnDelete();
            $table->decimal('quantity', 15, 3);
            $table->decimal('unit_cost', 15, 2);
            $table->timestamps();

            $table->index('invoice_product_id');
            $table->index('inventory_batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_product_costs');
    }
};
