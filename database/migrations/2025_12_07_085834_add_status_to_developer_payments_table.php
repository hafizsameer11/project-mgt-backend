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
        Schema::table('developer_payments', function (Blueprint $table) {
            $table->enum('status', ['Pending', 'Partial', 'Paid'])->default('Pending')->after('amount_paid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('developer_payments', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
