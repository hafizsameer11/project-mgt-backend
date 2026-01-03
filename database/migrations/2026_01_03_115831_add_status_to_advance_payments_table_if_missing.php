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
        if (Schema::hasTable('advance_payments')) {
            if (!Schema::hasColumn('advance_payments', 'status')) {
                Schema::table('advance_payments', function (Blueprint $table) {
                    $table->enum('status', ['pending', 'approved', 'paid'])->default('pending')->after('description');
                });
            }
            
            // Also ensure other columns exist
            if (!Schema::hasColumn('advance_payments', 'approved_by')) {
                Schema::table('advance_payments', function (Blueprint $table) {
                    $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null')->after('status');
                });
            }
            
            if (!Schema::hasColumn('advance_payments', 'approved_at')) {
                Schema::table('advance_payments', function (Blueprint $table) {
                    $table->timestamp('approved_at')->nullable()->after('approved_by');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('advance_payments')) {
            if (Schema::hasColumn('advance_payments', 'status')) {
                Schema::table('advance_payments', function (Blueprint $table) {
                    $table->dropColumn('status');
                });
            }
            
            if (Schema::hasColumn('advance_payments', 'approved_by')) {
                Schema::table('advance_payments', function (Blueprint $table) {
                    $table->dropForeign(['approved_by']);
                    $table->dropColumn('approved_by');
                });
            }
            
            if (Schema::hasColumn('advance_payments', 'approved_at')) {
                Schema::table('advance_payments', function (Blueprint $table) {
                    $table->dropColumn('approved_at');
                });
            }
        }
    }
};
