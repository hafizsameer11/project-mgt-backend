<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('push_subscriptions', function (Blueprint $table) {
            // Add unique constraint on expo_token to prevent duplicate tokens
            $table->unique('expo_token', 'push_subscriptions_expo_token_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('push_subscriptions', function (Blueprint $table) {
            try {
                $table->dropUnique('push_subscriptions_expo_token_unique');
            } catch (\Exception $e) {
                // Index might not exist, ignore
            }
        });
    }
};
