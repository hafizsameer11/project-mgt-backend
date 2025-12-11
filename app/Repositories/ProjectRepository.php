<?php

namespace App\Repositories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;

class ProjectRepository extends BaseRepository
{
    public function __construct(Project $model)
    {
        parent::__construct($model);
    }

    public function getByStatus(string $status): Collection
    {
        return $this->model->where('status', $status)->get();
    }

    public function getByClient(int $clientId): Collection
    {
        return $this->model->where('client_id', $clientId)->get();
    }

    public function getByAssignedBd(int $userId): Collection
    {
        return $this->model->where('assigned_bd', $userId)->get();
    }

    public function getWithTeams(int $id): ?Project
    {
        return $this->model->with('teams', 'client', 'assignedBd')->find($id);
    }
}

