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
        Schema::table('task_timers', function (Blueprint $table) {
            $table->timestamp('original_started_at')->nullable()->after('started_at');
            $table->timestamp('resumed_at')->nullable()->after('paused_at');
            $table->json('pause_history')->nullable()->after('total_seconds'); // Track all pause/resume events
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('task_timers', function (Blueprint $table) {
            $table->dropColumn(['original_started_at', 'resumed_at', 'pause_history']);
        });
    }
};
