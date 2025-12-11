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
        Schema::create('project_pm_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('pm_id')->constrained('users')->cascadeOnDelete();
            $table->enum('payment_type', ['percentage', 'fixed_amount'])->default('percentage');
            $table->decimal('percentage', 5, 2)->nullable();
            $table->decimal('fixed_amount', 15, 2)->nullable();
            $table->decimal('calculated_amount', 15, 2)->nullable();
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->enum('status', ['Pending', 'Partial', 'Paid'])->default('Pending');
            $table->text('payment_notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_pm_payments');
    }
};
