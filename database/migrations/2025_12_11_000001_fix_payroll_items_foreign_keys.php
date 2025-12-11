<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Fix foreign keys for payroll_items table if it exists
        if (Schema::hasTable('payroll_items')) {
            // Remove any existing foreign key constraints that might be broken
            $this->dropForeignKeyIfExists('payroll_items', 'payroll_items_payroll_id_foreign');
            
            // Add foreign key only if parent table exists
            if (Schema::hasTable('payroll')) {
                Schema::table('payroll_items', function (Blueprint $table) {
                    $table->foreign('payroll_id')->references('id')->on('payroll')->onDelete('cascade');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('payroll_items')) {
            Schema::table('payroll_items', function (Blueprint $table) {
                $this->dropForeignKeyIfExists('payroll_items', 'payroll_items_payroll_id_foreign');
            });
        }
    }
    
    private function dropForeignKeyIfExists($table, $foreignKey): void
    {
        try {
            DB::statement("ALTER TABLE {$table} DROP FOREIGN KEY {$foreignKey}");
        } catch (\Exception $e) {
            // Foreign key doesn't exist, ignore
        }
    }
};

