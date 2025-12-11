<?php

namespace App\Services;

use App\Repositories\TaskRepository;
use App\Repositories\ActivityLogRepository;
use App\Events\TaskCreated;

class TaskService
{
    protected $taskRepository;
    protected $activityLogRepository;

    public function __construct(
        TaskRepository $taskRepository,
        ActivityLogRepository $activityLogRepository
    ) {
        $this->taskRepository = $taskRepository;
        $this->activityLogRepository = $activityLogRepository;
    }

    public function getAll(array $filters = [], $user = null)
    {
        $query = \App\Models\Task::query();

        // Role-based filtering
        if ($user) {
            $role = $user->role;
            
            if ($role === 'Developer') {
                // Developers see only tasks assigned to them or created by them
                $query->where(function($q) use ($user) {
                    $q->where('assigned_to', $user->id)
                      ->orWhere('created_by', $user->id);
                });
            } elseif ($role === 'Project Manager') {
                // Project Managers see tasks for projects they manage
                // Get projects where PM is assigned as team member
                $team = \App\Models\Team::where('user_id', $user->id)
                    ->where('role', 'Project Manager')
                    ->first();
                
                if ($team) {
                    $projectIds = $team->projects()->pluck('projects.id');
                    $query->whereIn('project_id', $projectIds);
                } else {
                    // If no team found, show only tasks created by them
                    $query->where('created_by', $user->id);
                }
            }
            // Admin sees all tasks (no additional filtering)
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['assigned_to'])) {
            $query->where('assigned_to', $filters['assigned_to']);
        }

        if (isset($filters['project_id'])) {
            $query->where('project_id', $filters['project_id']);
        }

        return $query->with('project', 'assignedUser', 'creator')->paginate(15);
    }

    public function create(array $data, int $userId)
    {
        $task = $this->taskRepository->create($data);
        $this->logActivity($task, $userId, 'created', null, $data);

        event(new TaskCreated($task));

        return $task;
    }

    public function update(int $id, array $data, int $userId)
    {
        $task = $this->taskRepository->find($id);
        if (!$task) {
            return null;
        }

        $oldData = $task->toArray();
        $this->taskRepository->update($id, $data);
        $task->refresh();

        $this->logActivity($task, $userId, 'updated', $oldData, $task->toArray());

        return $task;
    }

    public function delete(int $id, int $userId): bool
    {
        $task = $this->taskRepository->find($id);
        if (!$task) {
            return false;
        }

        $oldData = $task->toArray();
        $this->logActivity($task, $userId, 'deleted', $oldData, null);
        
        return $this->taskRepository->delete($id);
    }

    public function startTimer(int $taskId, int $userId)
    {
        $task = $this->taskRepository->find($taskId);
        if (!$task) {
            return null;
        }

        $timer = \App\Models\TaskTimer::create([
            'task_id' => $taskId,
            'user_id' => $userId,
            'started_at' => now(),
        ]);

        return $timer;
    }

    public function stopTimer(int $timerId)
    {
        $timer = \App\Models\TaskTimer::find($timerId);
        if (!$timer) {
            return null;
        }

        $startedAt = $timer->started_at;
        $stoppedAt = now();
        $seconds = $startedAt->diffInSeconds($stoppedAt);

        $timer->update([
            'stopped_at' => $stoppedAt,
            'total_seconds' => ($timer->total_seconds ?? 0) + $seconds,
        ]);

        return $timer;
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

