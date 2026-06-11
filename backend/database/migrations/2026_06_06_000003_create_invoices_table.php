<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('code', 20);
            $table->foreignId('party_id')->constrained('parties')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('description')->nullable();
            $table->date('invoice_date');
            $table->string('payment_method', 20);
            $table->string('payment_status', 20)->default('unpaid');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_total', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['store_id', 'code']);
            $table->index(['store_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
