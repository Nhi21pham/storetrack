<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taggables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();
            $table->foreignId('tag_value_id')->nullable()->constrained('tag_values')->cascadeOnDelete();
            $table->string('taggable_type');
            $table->unsignedBigInteger('taggable_id');
            $table->timestamps();

            $table->index(['taggable_type', 'taggable_id']);
            $table->unique(
                ['taggable_type', 'taggable_id', 'tag_id', 'tag_value_id'],
                'taggables_entity_tag_value_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taggables');
    }
};
