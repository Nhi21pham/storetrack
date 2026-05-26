<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('name', 50);
            $table->string('name_normalized', 50);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['business_id', 'name_normalized']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
