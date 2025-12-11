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
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->decimal('budget', 15, 2)->nullable();
            $table->text('description')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('project_type')->nullable();
            $table->enum('priority', ['Low', 'Medium', 'High', 'Critical'])->nullable();
            $table->enum('status', ['Planning', 'In Progress', 'On Hold', 'Completed', 'Cancelled'])->default('Planning');
            $table->foreignId('assigned_bd')->nullable()->constrained('users')->nullOnDelete();
            $table->json('attachments')->nullable();
            $table->json('tags')->nullable();
            $table->string('repo_link')->nullable();
            $table->string('server_url')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};

