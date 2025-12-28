<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('planned_expenses')) {
            Schema::create('planned_expenses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('expense_category_id')->nullable()->constrained('expense_categories')->nullOnDelete();
                $table->string('name');
                $table->text('description')->nullable();
                $table->decimal('amount', 15, 2);
                $table->string('currency', 3)->default('PKR');
                $table->integer('day_of_month')->comment('Day of month when expense is due (1-31)');
                $table->boolean('is_active')->default(true);
                $table->boolean('is_recurring')->default(true)->comment('If false, expense is one-time for specific month');
                $table->date('start_date')->nullable()->comment('Start date for recurring expenses');
                $table->date('end_date')->nullable()->comment('End date for recurring expenses (null = ongoing)');
                $table->date('specific_month')->nullable()->comment('For one-time expenses, the specific month');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('planned_expenses');
    }
};
