<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('payroll')) {
            Schema::create('payroll', function (Blueprint $table) {
                $table->id();
                
                // Create columns first
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('team_id')->nullable();
                $table->unsignedBigInteger('processed_by')->nullable();
                
                $table->string('payroll_no')->unique();
                $table->date('pay_period_start');
                $table->date('pay_period_end');
                $table->date('pay_date');
                $table->decimal('gross_salary', 15, 2);
                $table->decimal('total_deductions', 15, 2)->default(0);
                $table->decimal('total_allowances', 15, 2)->default(0);
                $table->decimal('net_salary', 15, 2);
                $table->enum('status', ['draft', 'processed', 'paid', 'cancelled'])->default('draft');
                $table->text('notes')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();
                
                // Add foreign keys separately after table creation
                if (Schema::hasTable('users')) {
                    $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                    $table->foreign('processed_by')->references('id')->on('users')->onDelete('set null');
                }
                
                if (Schema::hasTable('teams')) {
                    $table->foreign('team_id')->references('id')->on('teams')->onDelete('set null');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll');
    }
};
