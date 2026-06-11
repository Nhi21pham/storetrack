<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_product_taxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_product_id')->constrained('invoice_products')->cascadeOnDelete();
            $table->foreignId('tax_id')->nullable()->constrained('taxes')->nullOnDelete();
            $table->string('tax_name', 100);
            $table->decimal('tax_rate', 8, 4);
            $table->decimal('tax_amount', 15, 2);
            $table->timestamps();

            $table->index('invoice_product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_product_taxes');
    }
};
