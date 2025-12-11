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
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
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
                $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll');
    }
};
