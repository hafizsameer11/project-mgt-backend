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
        Schema::create('project_team_client_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
            $table->foreignId('project_id')->nullable()->constrained('projects')->onDelete('cascade');
            $table->foreignId('team_id')->constrained('teams')->onDelete('cascade');
            $table->string('display_name');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            // Unique constraint: one alias per client-project-team combination
            // If project_id is null, it's a global alias for that client
            $table->unique(['client_id', 'project_id', 'team_id'], 'unique_client_project_team_alias');
            $table->index(['client_id', 'team_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_team_client_aliases');
    }
};
