<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectTeamClientAlias extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'project_id',
        'team_id',
        'display_name',
        'notes',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get display name for a team member for a specific client/project
     * Returns the alias if exists, otherwise returns the team member's name
     */
    public static function getDisplayName(int $clientId, int $teamId, ?int $projectId = null): string
    {
        // First try to find project-specific alias
        if ($projectId) {
            $alias = self::where('client_id', $clientId)
                ->where('project_id', $projectId)
                ->where('team_id', $teamId)
                ->first();
            
            if ($alias) {
                return $alias->display_name;
            }
        }

        // Then try to find global alias for this client
        $alias = self::where('client_id', $clientId)
            ->whereNull('project_id')
            ->where('team_id', $teamId)
            ->first();

        if ($alias) {
            return $alias->display_name;
        }

        // Fallback to team member's actual name
        $team = Team::with('user')->find($teamId);
        return $team ? ($team->user->name ?? $team->full_name) : 'Unknown';
    }
}

