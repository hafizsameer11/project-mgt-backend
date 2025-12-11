<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('purchase_orders')) {
            Schema::create('purchase_orders', function (Blueprint $table) {
                $table->id();
                $table->string('po_number')->unique();
                
                // Create vendor_id column first
                $table->unsignedBigInteger('vendor_id');
                
                // Create project_id column
                $table->unsignedBigInteger('project_id')->nullable();
                
                $table->date('order_date');
                $table->date('expected_delivery_date')->nullable();
                $table->decimal('total_amount', 15, 2)->default(0);
                $table->enum('status', ['draft', 'sent', 'confirmed', 'received', 'cancelled'])->default('draft');
                $table->text('notes')->nullable();
                
                // Create created_by column
                $table->unsignedBigInteger('created_by');
                
                $table->timestamps();
                
                // Add foreign keys separately after table creation
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
        Schema::dropIfExists('purchase_orders');
    }
};
