<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banks', function (Blueprint $table) {
            $table->id();
            $table->string('short_name', 50);
            $table->string('full_name_vi', 255);
            $table->string('full_name_en', 255);
            $table->string('short_name_normalized', 50);
            $table->string('full_name_vi_normalized', 255);
            $table->string('full_name_en_normalized', 255);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('short_name_normalized');
            $table->unique('full_name_vi_normalized');
            $table->unique('full_name_en_normalized');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banks');
    }
};
