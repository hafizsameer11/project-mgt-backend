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
        Schema::create('project_bd_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('bd_id')->constrained('users')->cascadeOnDelete();
            $table->enum('payment_type', ['percentage', 'fixed_amount'])->default('percentage');
            $table->decimal('percentage', 5, 2)->nullable(); // e.g., 10.50 for 10.5%
            $table->decimal('fixed_amount', 15, 2)->nullable();
            $table->decimal('calculated_amount', 15, 2)->nullable(); // Calculated based on project budget
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->text('payment_notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_bd_payments');
    }
};

