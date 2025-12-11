<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('payroll_items')) {
            Schema::create('payroll_items', function (Blueprint $table) {
                $table->id();
                
                // Create payroll_id column first
                $table->unsignedBigInteger('payroll_id');
                
                $table->enum('type', ['earning', 'deduction'])->default('earning');
                $table->string('item_name');
                $table->decimal('amount', 15, 2);
                $table->text('description')->nullable();
                $table->timestamps();
                
                // Add foreign key separately after table creation
                if (Schema::hasTable('payroll')) {
                    $table->foreign('payroll_id')->references('id')->on('payroll')->onDelete('cascade');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_items');
    }
};
