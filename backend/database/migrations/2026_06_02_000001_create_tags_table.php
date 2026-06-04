<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('name_normalized', 100);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'name_normalized']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tags');
    }
};
