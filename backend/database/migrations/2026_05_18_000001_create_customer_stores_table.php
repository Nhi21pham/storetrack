<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_stores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['customer_id', 'store_id']);
            $table->index('store_id');
        });

        DB::table('customers')
            ->whereNotNull('store_id')
            ->orderBy('id')
            ->each(function ($customer) {
                DB::table('customer_stores')->insertOrIgnore([
                    'customer_id' => $customer->id,
                    'store_id'    => $customer->store_id,
                    'created_at'  => $customer->created_at,
                    'updated_at'  => $customer->updated_at,
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_stores');
    }
};
