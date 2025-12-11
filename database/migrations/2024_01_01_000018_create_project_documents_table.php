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
        Schema::create('project_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('title');
            $table->enum('type', [
                'Document',
                'GitHub Credentials',
                'Server Credentials',
                'Database Credentials',
                'API Keys',
                'Domain Credentials',
                'Hosting Credentials',
                'Other'
            ])->default('Document');
            $table->text('description')->nullable();
            $table->string('file_path')->nullable();
            $table->text('credentials')->nullable(); // Encrypted JSON for credentials
            $table->string('url')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_documents');
    }
};

