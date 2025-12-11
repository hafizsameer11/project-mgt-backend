<?php

namespace App\Repositories;

use App\Models\Task;
use Illuminate\Database\Eloquent\Collection;

class TaskRepository extends BaseRepository
{
    public function __construct(Task $model)
    {
        parent::__construct($model);
    }

    public function getByStatus(string $status): Collection
    {
        return $this->model->where('status', $status)->get();
    }

    public function getByAssignedTo(int $userId): Collection
    {
        return $this->model->where('assigned_to', $userId)->get();
    }

    public function getByProject(int $projectId): Collection
    {
        return $this->model->where('project_id', $projectId)->get();
    }

    public function getDueToday(): Collection
    {
        return $this->model->where('deadline', today())
            ->where('status', '!=', 'Completed')
            ->get();
    }

    public function getByTaskType(string $taskType): Collection
    {
        return $this->model->where('task_type', $taskType)->get();
    }
}

