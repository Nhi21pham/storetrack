<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_invitations', function (Blueprint $table) {
            $table->unique(['store_id', 'invitee_email', 'status'], 'unique_active_invitation');
        });
    }

    public function down(): void
    {
        Schema::table('store_invitations', function (Blueprint $table) {
            $table->dropUnique('unique_active_invitation');
        });
    }
};
