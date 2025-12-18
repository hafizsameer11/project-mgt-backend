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
            // Drop the unique constraint on endpoint first
            $table->dropUnique(['endpoint']);
            // Make endpoint nullable
            $table->string('endpoint', 500)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('push_subscriptions', function (Blueprint $table) {
            // Make endpoint required again
            $table->string('endpoint', 500)->nullable(false)->change();
            // Add unique constraint back
            $table->unique('endpoint');
        });
    }
};
