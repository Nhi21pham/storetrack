<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tag_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();
            $table->string('value', 100);
            $table->string('value_normalized', 100);
            $table->timestamps();

            $table->unique(['tag_id', 'value_normalized']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tag_values');
    }
};
