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
        Schema::table('developer_payment_history', function (Blueprint $table) {
            $table->string('invoice_no')->nullable()->after('invoice_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('developer_payment_history', function (Blueprint $table) {
            $table->dropColumn('invoice_no');
        });
    }
};
