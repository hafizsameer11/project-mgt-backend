<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('assets')) {
            Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_code')->unique();
            $table->string('asset_name');
            $table->enum('asset_type', ['fixed', 'current', 'intangible'])->default('fixed');
            $table->enum('category', ['equipment', 'vehicle', 'furniture', 'software', 'building', 'other'])->default('equipment');
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_cost', 15, 2);
            $table->decimal('current_value', 15, 2)->nullable();
            $table->enum('depreciation_method', ['straight_line', 'declining_balance', 'none'])->default('straight_line');
            $table->integer('useful_life_years')->nullable();
            $table->decimal('depreciation_rate', 5, 2)->nullable();
            $table->enum('status', ['active', 'disposed', 'maintenance', 'retired'])->default('active');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('location')->nullable();
            $table->text('description')->nullable();
            $table->text('serial_number')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
