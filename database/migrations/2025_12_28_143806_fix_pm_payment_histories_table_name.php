<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Check if old singular table exists
        if (Schema::hasTable('pm_payment_history') && !Schema::hasTable('pm_payment_histories')) {
            // Rename the table to plural form
            Schema::rename('pm_payment_history', 'pm_payment_histories');
        } elseif (!Schema::hasTable('pm_payment_histories')) {
            // Create the table if it doesn't exist at all
            Schema::create('pm_payment_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_pm_payment_id')->constrained('project_pm_payments')->cascadeOnDelete();
                $table->decimal('amount', 15, 2);
                $table->date('payment_date');
                $table->string('invoice_path')->nullable();
                $table->string('invoice_no')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // If we renamed, rename back
        if (Schema::hasTable('pm_payment_histories') && !Schema::hasTable('pm_payment_history')) {
            Schema::rename('pm_payment_histories', 'pm_payment_history');
        } elseif (Schema::hasTable('pm_payment_histories')) {
            Schema::dropIfExists('pm_payment_histories');
        }
    }
};
