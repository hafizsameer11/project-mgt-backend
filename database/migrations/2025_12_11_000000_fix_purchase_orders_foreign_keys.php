<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Fix foreign keys for purchase_orders table if it exists
        if (Schema::hasTable('purchase_orders')) {
            // Remove any existing foreign key constraints that might be broken
            $this->dropForeignKeyIfExists('purchase_orders', 'purchase_orders_vendor_id_foreign');
            $this->dropForeignKeyIfExists('purchase_orders', 'purchase_orders_project_id_foreign');
            $this->dropForeignKeyIfExists('purchase_orders', 'purchase_orders_created_by_foreign');
            
            // Add foreign keys only if parent tables exist
            Schema::table('purchase_orders', function (Blueprint $table) {
                if (Schema::hasTable('vendors')) {
                    $table->foreign('vendor_id')->references('id')->on('vendors')->onDelete('cascade');
                }
                
                if (Schema::hasTable('projects')) {
                    $table->foreign('project_id')->references('id')->on('projects')->onDelete('set null');
                }
                
                if (Schema::hasTable('users')) {
                    $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('purchase_orders')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $this->dropForeignKeyIfExists('purchase_orders', 'purchase_orders_vendor_id_foreign');
                $this->dropForeignKeyIfExists('purchase_orders', 'purchase_orders_project_id_foreign');
                $this->dropForeignKeyIfExists('purchase_orders', 'purchase_orders_created_by_foreign');
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

