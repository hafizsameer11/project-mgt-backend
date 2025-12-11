<?php

namespace App\Services;

use App\Repositories\ProjectRepository;
use App\Repositories\ActivityLogRepository;
use Illuminate\Support\Facades\DB;

class ProjectService
{
    protected $projectRepository;
    protected $activityLogRepository;

    public function __construct(
        ProjectRepository $projectRepository,
        ActivityLogRepository $activityLogRepository
    ) {
        $this->projectRepository = $projectRepository;
        $this->activityLogRepository = $activityLogRepository;
    }

    public function getAll(array $filters = [], $user = null)
    {
        $query = \App\Models\Project::query();

        // Role-based filtering
        if ($user && $user->role === 'Project Manager') {
            // Project Managers see only projects they manage
            $team = \App\Models\Team::where('user_id', $user->id)
                ->where('role', 'Project Manager')
                ->first();
            
            if ($team) {
                $projectIds = $team->projects()->pluck('projects.id');
                $query->whereIn('id', $projectIds);
            } else {
                // If no team found, show empty result
                $query->whereRaw('1 = 0');
            }
        }
        // Admin and other roles see all projects (no additional filtering)

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['client_id'])) {
            $query->where('client_id', $filters['client_id']);
        }

        return $query->with('client', 'assignedBd', 'teams')->paginate(15);
    }

    public function create(array $data, int $userId)
    {
        $project = $this->projectRepository->create($data);

        if (isset($data['team_ids'])) {
            $teamData = [];
            foreach ($data['team_ids'] as $teamId) {
                $teamData[$teamId] = [
                    'assigned_amount' => $data['team_amounts'][$teamId] ?? null,
                ];
            }
            $project->teams()->attach($teamData);
        }

        $this->logActivity($project, $userId, 'created', null, $data);

        return $project->load('teams');
    }

    public function update(int $id, array $data, int $userId)
    {
        $project = $this->projectRepository->find($id);
        if (!$project) {
            return null;
        }

        $oldData = $project->toArray();
        $this->projectRepository->update($id, $data);

        if (isset($data['team_ids'])) {
            $teamData = [];
            foreach ($data['team_ids'] as $teamId) {
                $teamData[$teamId] = [
                    'assigned_amount' => $data['team_amounts'][$teamId] ?? null,
                ];
            }
            $project->teams()->sync($teamData);
        }

        $project->refresh();
        $this->logActivity($project, $userId, 'updated', $oldData, $project->toArray());

        return $project->load('teams');
    }

    public function delete(int $id, int $userId): bool
    {
        $project = $this->projectRepository->find($id);
        if (!$project) {
            return false;
        }

        $oldData = $project->toArray();
        $this->logActivity($project, $userId, 'deleted', $oldData, null);
        
        return $this->projectRepository->delete($id);
    }

    public function assignTeam(int $projectId, int $teamId, float $amount = null)
    {
        $project = $this->projectRepository->find($projectId);
        if (!$project) {
            return false;
        }

        $project->teams()->syncWithoutDetaching([
            $teamId => ['assigned_amount' => $amount],
        ]);

        return true;
    }

    protected function logActivity($model, int $userId, string $action, $oldValue = null, $newValue = null)
    {
        $this->activityLogRepository->create([
            'model_type' => get_class($model),
            'model_id' => $model->id,
            'user_id' => $userId,
            'action' => $action,
            'old_value' => $oldValue,
            'new_value' => $newValue,
        ]);
    }
}

