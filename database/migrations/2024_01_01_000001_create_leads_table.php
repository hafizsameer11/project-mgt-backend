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
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->enum('source', ['Facebook', 'Upwork', 'Fiverr', 'Website', 'Referral', 'Other'])->nullable();
            $table->decimal('estimated_budget', 15, 2)->nullable();
            $table->enum('lead_status', ['New', 'In Progress', 'Converted', 'Lost'])->default('New');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->date('follow_up_date')->nullable();
            $table->json('attachments')->nullable();
            $table->date('conversion_date')->nullable();
            $table->unsignedBigInteger('converted_client_id')->nullable();
            $table->unsignedBigInteger('project_id_after_conversion')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};

