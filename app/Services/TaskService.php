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
                    // Show all tasks EXCEPT admin's personal tasks (where both created_by AND assigned_to are admin)
                    // This includes: tasks created by admin for developers, tasks created by others, etc.
                    $query->where(function($q) use ($user) {
                        $q->whereRaw('NOT (created_by = ? AND assigned_to = ?)', [$user->id, $user->id]);
                    });
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

        // Apply exclude filters only if not already handled in role-based filtering above
        $excludeAlreadyHandled = false;
        if ($user && $user->role === 'Admin' && 
            isset($filters['exclude_created_by']) && $filters['exclude_created_by'] == $user->id &&
            isset($filters['exclude_assigned_to']) && $filters['exclude_assigned_to'] == $user->id) {
            $excludeAlreadyHandled = true;
        }

        if (!$excludeAlreadyHandled) {
            if (isset($filters['exclude_created_by'])) {
                $query->where('created_by', '!=', $filters['exclude_created_by']);
            }

            if (isset($filters['exclude_assigned_to'])) {
                $query->where('assigned_to', '!=', $filters['exclude_assigned_to']);
            }
        }

        if (isset($filters['project_id'])) {
            $query->where('project_id', $filters['project_id']);
        }

        return $query->with('project', 'assignedUser', 'creator')->paginate(150);
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

        // Check if there's already an active timer (not stopped) for this task and user
        $existingTimer = \App\Models\TaskTimer::where('task_id', $taskId)
            ->where('user_id', $userId)
            ->whereNull('stopped_at')
            ->latest()
            ->first();

        if ($existingTimer) {
            // Check if the existing timer is paused
            // Timer is paused if paused_at is set AND (resumed_at is null OR paused_at is after resumed_at)
            $isPaused = $existingTimer->paused_at && 
                (!$existingTimer->resumed_at || $existingTimer->paused_at->isAfter($existingTimer->resumed_at));
            
            if ($isPaused) {
                // Timer is paused, resume it instead of creating a new one
                return $this->resumeTimer($existingTimer->id);
            } else {
                // Timer is already running, return it
                return $existingTimer;
            }
        }

        // No active timer exists, create a new one
        $now = now();
        $timer = \App\Models\TaskTimer::create([
            'task_id' => $taskId,
            'user_id' => $userId,
            'started_at' => $now,
            'original_started_at' => $now,
            'pause_history' => [],
        ]);

        return $timer;
    }

    public function pauseTimer(int $timerId)
    {
        $timer = \App\Models\TaskTimer::find($timerId);
        if (!$timer || $timer->stopped_at) {
            return null;
        }

        // Check if timer is currently running (not paused)
        // Timer is running if paused_at is null OR (resumed_at is set AND resumed_at timestamp >= paused_at timestamp)
        $isRunning = false;
        if (!$timer->paused_at) {
            // No paused_at means timer is running
            $isRunning = true;
        } else {
            // paused_at exists, check if it's been resumed
            if (!$timer->resumed_at) {
                // paused_at exists but no resumed_at means it's paused (not running)
                $isRunning = false;
            } else {
                // Compare timestamps to see which is more recent
                $pausedTimestamp = $timer->paused_at->getTimestamp();
                $resumedTimestamp = $timer->resumed_at->getTimestamp();
                $isRunning = $resumedTimestamp >= $pausedTimestamp;
            }
        }
        
        if (!$isRunning) {
            return null; // Timer is already paused
        }

        $startedAt = $timer->started_at;
        $pausedAt = now();
        $seconds = $startedAt->diffInSeconds($pausedAt);

        // Add pause event to history
        $pauseHistory = $timer->pause_history ?? [];
        $pauseHistory[] = [
            'type' => 'pause',
            'at' => $pausedAt->toISOString(),
            'session_started_at' => $startedAt->toISOString(),
            'seconds_before_pause' => $seconds,
        ];

        $timer->update([
            'paused_at' => $pausedAt,
            'total_seconds' => ($timer->total_seconds ?? 0) + $seconds,
            'pause_history' => $pauseHistory,
        ]);

        return $timer;
    }

    public function resumeTimer(int $timerId)
    {
        $timer = \App\Models\TaskTimer::find($timerId);
        if (!$timer || $timer->stopped_at) {
            return null;
        }

        // Check if timer is currently paused
        // Timer is paused if paused_at is set AND (resumed_at is null OR paused_at timestamp > resumed_at timestamp)
        $isPaused = false;
        if ($timer->paused_at) {
            if (!$timer->resumed_at) {
                // paused_at exists but no resumed_at means it's paused
                $isPaused = true;
            } else {
                // Compare timestamps to see which is more recent
                $pausedTimestamp = $timer->paused_at->getTimestamp();
                $resumedTimestamp = $timer->resumed_at->getTimestamp();
                $isPaused = $pausedTimestamp > $resumedTimestamp;
            }
        }
        
        if (!$isPaused) {
            return null;
        }

        $resumedAt = now();
        $pausedAt = $timer->paused_at;

        // Add resume event to history (keep paused_at for history)
        $pauseHistory = $timer->pause_history ?? [];
        $pauseHistory[] = [
            'type' => 'resume',
            'at' => $resumedAt->toISOString(),
            'paused_at' => $pausedAt->toISOString(),
            'pause_duration_seconds' => $pausedAt->diffInSeconds($resumedAt),
        ];

        $timer->update([
            'started_at' => $resumedAt, // New session start time
            'resumed_at' => $resumedAt,
            'pause_history' => $pauseHistory,
            // Keep paused_at for history - don't clear it
        ]);

        return $timer;
    }

    public function stopTimer(int $timerId)
    {
        $timer = \App\Models\TaskTimer::find($timerId);
        if (!$timer || $timer->stopped_at) {
            return null;
        }

        $stoppedAt = now();
        $seconds = 0;

        // Check if timer is currently paused
        $isPaused = false;
        if ($timer->paused_at) {
            if (!$timer->resumed_at) {
                // paused_at exists but no resumed_at means it's paused
                $isPaused = true;
            } else {
                // Compare timestamps to see which is more recent
                $pausedTimestamp = $timer->paused_at->getTimestamp();
                $resumedTimestamp = $timer->resumed_at->getTimestamp();
                $isPaused = $pausedTimestamp > $resumedTimestamp;
            }
        }

        if ($isPaused) {
            // Already paused, use existing total_seconds
            $seconds = $timer->total_seconds ?? 0;
        } else {
            // Still running, calculate seconds from current started_at
            $startedAt = $timer->started_at;
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

