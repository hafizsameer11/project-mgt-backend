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
        if (Schema::hasTable('developer_payment_history') && !Schema::hasTable('developer_payment_histories')) {
            // Rename the table to plural form
            Schema::rename('developer_payment_history', 'developer_payment_histories');
        } elseif (!Schema::hasTable('developer_payment_histories')) {
            // Create the table if it doesn't exist at all
            Schema::create('developer_payment_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('developer_payment_id')->constrained('developer_payments')->cascadeOnDelete();
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
        if (Schema::hasTable('developer_payment_histories') && !Schema::hasTable('developer_payment_history')) {
            Schema::rename('developer_payment_histories', 'developer_payment_history');
        } elseif (Schema::hasTable('developer_payment_histories')) {
            Schema::dropIfExists('developer_payment_histories');
        }
    }
};
