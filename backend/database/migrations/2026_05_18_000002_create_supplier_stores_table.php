<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_stores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['supplier_id', 'store_id']);
            $table->index('store_id');
        });

        DB::table('suppliers')
            ->whereNotNull('store_id')
            ->orderBy('id')
            ->each(function ($supplier) {
                DB::table('supplier_stores')->insertOrIgnore([
                    'supplier_id' => $supplier->id,
                    'store_id'    => $supplier->store_id,
                    'created_at'  => $supplier->created_at,
                    'updated_at'  => $supplier->updated_at,
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_stores');
    }
};
