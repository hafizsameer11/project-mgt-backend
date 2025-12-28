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
        if (Schema::hasTable('bd_payment_history') && !Schema::hasTable('bd_payment_histories')) {
            // Rename the table to plural form
            Schema::rename('bd_payment_history', 'bd_payment_histories');
        } elseif (!Schema::hasTable('bd_payment_histories')) {
            // Create the table if it doesn't exist at all
            Schema::create('bd_payment_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('bd_payment_id')->constrained('project_bd_payments')->cascadeOnDelete();
                $table->decimal('amount', 15, 2);
                $table->date('payment_date');
                $table->text('notes')->nullable();
                $table->string('invoice_path')->nullable();
                $table->string('invoice_no')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // If we renamed, rename back
        if (Schema::hasTable('bd_payment_histories') && !Schema::hasTable('bd_payment_history')) {
            Schema::rename('bd_payment_histories', 'bd_payment_history');
        } elseif (Schema::hasTable('bd_payment_histories')) {
            Schema::dropIfExists('bd_payment_histories');
        }
    }
};
