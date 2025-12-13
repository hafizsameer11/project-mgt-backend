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

    public function find(int $id)
    {
        return $this->taskRepository->find($id);
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
                // Project Managers see:
                // 1. Tasks for projects they manage (where they're assigned as PM)
                // 2. Tasks assigned to them (regardless of project)
                // 3. Tasks they created (only for their projects)
                $team = \App\Models\Team::where('user_id', $user->id)
                    ->where('role', 'Project Manager')
                    ->first();
                
                if ($team) {
                    $projectIds = $team->projects()->pluck('projects.id');
                    $query->where(function($q) use ($user, $projectIds) {
                        // Tasks for their projects
                        $q->whereIn('project_id', $projectIds)
                          // OR tasks assigned to them (any project)
                          ->orWhere('assigned_to', $user->id)
                          // OR tasks they created for their projects
                          ->orWhere(function($subQ) use ($user, $projectIds) {
                              $subQ->where('created_by', $user->id)
                                   ->whereIn('project_id', $projectIds);
                          });
                    })
                    // Exclude tasks created by Admin for themselves (unless for PM's project)
                    ->where(function($q) use ($projectIds) {
                        // If task is for PM's project, always show it
                        $q->whereIn('project_id', $projectIds->toArray())
                        // OR if not created by Admin for themselves
                        ->orWhereRaw('NOT (created_by = assigned_to AND created_by IN (SELECT id FROM users WHERE role = "Admin"))');
                    });
                } else {
                    // If no team found, show only tasks assigned to them or created by them
                    $query->where(function($q) use ($user) {
                        $q->where('assigned_to', $user->id)
                          ->orWhere('created_by', $user->id);
                    })
                    // Exclude Admin's personal tasks
                    ->whereRaw('NOT (created_by = assigned_to AND created_by IN (SELECT id FROM users WHERE role = "Admin"))');
                }
            } elseif ($role === 'Admin') {
                // Admin sees all tasks EXCEPT:
                // Tasks created by PM for themselves (where created_by = assigned_to and creator is PM)
                // But if filters specify created_by and assigned_to, show only that admin's tasks
                if (isset($filters['created_by']) && $filters['created_by'] == $user->id && 
                    isset($filters['assigned_to']) && $filters['assigned_to'] == $user->id) {
                    // Show only admin's personal tasks (created by and assigned to admin)
                    // Filters will be applied later, so just skip the default filtering
                } elseif (isset($filters['exclude_created_by']) && $filters['exclude_created_by'] == $user->id &&
                          isset($filters['exclude_assigned_to']) && $filters['exclude_assigned_to'] == $user->id) {
                    // Show tasks NOT created by admin and NOT assigned to admin
                    // Filters will be applied later, so just skip the default filtering
                } else {
                    // Default: show all tasks except PM's personal tasks
                    $query->where(function($q) {
                        $q->whereRaw('NOT (created_by = assigned_to AND created_by IN (SELECT id FROM users WHERE role = "Project Manager"))');
                    });
                }
            }
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['assigned_to'])) {
            $query->where('assigned_to', $filters['assigned_to']);
        }

        if (isset($filters['created_by'])) {
            $query->where('created_by', $filters['created_by']);
        }

        if (isset($filters['exclude_created_by'])) {
            $query->where('created_by', '!=', $filters['exclude_created_by']);
        }

        if (isset($filters['exclude_assigned_to'])) {
            $query->where('assigned_to', '!=', $filters['exclude_assigned_to']);
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

    public function pauseTimer(int $timerId)
    {
        $timer = \App\Models\TaskTimer::find($timerId);
        if (!$timer || $timer->stopped_at || $timer->paused_at) {
            return null;
        }

        $startedAt = $timer->started_at;
        $pausedAt = now();
        $seconds = $startedAt->diffInSeconds($pausedAt);

        $timer->update([
            'paused_at' => $pausedAt,
            'total_seconds' => ($timer->total_seconds ?? 0) + $seconds,
        ]);

        return $timer;
    }

    public function resumeTimer(int $timerId)
    {
        $timer = \App\Models\TaskTimer::find($timerId);
        if (!$timer || $timer->stopped_at || !$timer->paused_at) {
            return null;
        }

        $timer->update([
            'started_at' => now(),
            'paused_at' => null,
        ]);

        return $timer;
    }

    public function stopTimer(int $timerId)
    {
        $timer = \App\Models\TaskTimer::find($timerId);
        if (!$timer || $timer->stopped_at) {
            return null;
        }

        $startedAt = $timer->started_at;
        $stoppedAt = now();
        $seconds = 0;

        if ($timer->paused_at) {
            // Already paused, use existing total_seconds
            $seconds = $timer->total_seconds ?? 0;
        } else {
            // Still running, calculate seconds
            $seconds = ($timer->total_seconds ?? 0) + $startedAt->diffInSeconds($stoppedAt);
        }

        $timer->update([
            'stopped_at' => $stoppedAt,
            'total_seconds' => $seconds,
        ]);

        // Calculate total hours and update task
        $task = $timer->task;
        if ($task) {
            $totalSeconds = \App\Models\TaskTimer::where('task_id', $task->id)
                ->whereNotNull('stopped_at')
                ->sum('total_seconds');
            
            $totalHours = round($totalSeconds / 3600, 2);
            
            $task->update([
                'actual_time' => $totalHours,
                'status' => 'Review',
            ]);
        }

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

