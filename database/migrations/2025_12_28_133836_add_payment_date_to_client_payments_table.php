<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_payments', function (Blueprint $table) {
            $table->date('payment_date')->nullable()->after('amount_paid');
        });
        
        // Set payment_date for existing records based on when amount_paid was set
        // For records with amount_paid > 0, use updated_at as payment_date
        DB::statement("
            UPDATE client_payments 
            SET payment_date = DATE(updated_at) 
            WHERE amount_paid > 0 AND payment_date IS NULL
        ");
    }

    public function down(): void
    {
        Schema::table('client_payments', function (Blueprint $table) {
            $table->dropColumn('payment_date');
        });
    }
};
