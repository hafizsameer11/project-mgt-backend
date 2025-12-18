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
            $table->string('expo_token')->nullable()->after('endpoint');
            $table->enum('device_type', ['ios', 'android', 'web'])->nullable()->after('expo_token');
            $table->index(['user_id', 'expo_token']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('push_subscriptions', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'expo_token']);
            $table->dropColumn(['expo_token', 'device_type']);
        });
    }
};
