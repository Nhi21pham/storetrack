<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inviter_id')->constrained('users')->cascadeOnDelete();
            $table->string('invitee_email');
            $table->string('role');
            $table->string('token', 64)->unique();
            $table->string('status')->default('pending'); // pending, accepted, declined, expired, cancelled
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'status']);
            $table->index(['invitee_email', 'store_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_invitations');
    }
};
