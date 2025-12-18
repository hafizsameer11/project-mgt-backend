<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Services\TaskService;
use App\Services\FileUploadService;
use App\Notifications\TaskAssignedNotification;
use App\Notifications\TaskCreatedNotification;
use App\Notifications\TaskUpdatedNotification;
use App\Notifications\TaskStatusChangedNotification;
use App\Notifications\TaskDeletedNotification;
use App\Notifications\TaskStartedNotification;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    protected $taskService;
    protected $fileUploadService;

    public function __construct(TaskService $taskService, FileUploadService $fileUploadService)
    {
        $this->taskService = $taskService;
        $this->fileUploadService = $fileUploadService;
    }

    public function index(Request $request)
    {
        $tasks = $this->taskService->getAll($request->all(), $request->user());
        return TaskResource::collection($tasks);
    }

    public function store(StoreTaskRequest $request)
    {
        $data = $request->validated();
        $user = $request->user();
        
        // Set created_by if not provided
        if (!isset($data['created_by'])) {
            $data['created_by'] = $user->id;
        }
        
        // If assigned_to is not provided or is empty, assign to creator (their own task)
        if (!isset($data['assigned_to']) || $data['assigned_to'] === null || $data['assigned_to'] === '') {
            $data['assigned_to'] = $data['created_by'];
        }
        
        // Handle file uploads
        if ($request->hasFile('attachments')) {
            $data['attachments'] = $this->fileUploadService->uploadMultiple(
                $request->file('attachments'),
                'tasks'
            );
        }
        
        $requirementIds = $request->input('requirement_ids', []);
        $task = $this->taskService->create($data, $user->id);
        
        // Sync requirements
        if (!empty($requirementIds)) {
            $task->requirements()->sync($requirementIds);
        }
        
        $task->load('project', 'assignedUser', 'creator');
        
        // Notify assigned user (if different from creator)
        if ($task->assigned_to && $task->assigned_to !== $user->id) {
            $assignedUser = \App\Models\User::find($task->assigned_to);
            if ($assignedUser) {
                $assignedUser->notify(new TaskAssignedNotification($task));
            }
        }
        
        // Notify admin + creator (if admin created task, notify admin; if user created, notify admin)
        $admins = \App\Models\User::where('role', 'Admin')->get();
        foreach ($admins as $admin) {
            if ($admin->id !== $user->id) {
                $admin->notify(new TaskCreatedNotification($task));
            }
        }
        
        // Notify creator if they're not admin and task is assigned to someone else
        if ($user->role !== 'Admin' && $task->assigned_to !== $user->id) {
            $user->notify(new TaskCreatedNotification($task));
        }
        
        return new TaskResource($task->load('project', 'assignedUser', 'creator', 'requirements'));
    }

    public function show(int $id)
    {
        $task = $this->taskService->find($id);
        if (!$task) {
            return response()->json(['message' => 'Task not found'], 404);
        }
        // Load timers with user relationship and order by most recent
        $task->load(['project', 'assignedUser', 'creator', 'requirements', 'timers' => function($query) {
            $query->orderBy('created_at', 'desc');
        }]);
        return new TaskResource($task);
    }

    public function update(UpdateTaskRequest $request, int $id)
    {
        $data = $request->validated();
        
        // Get task before update to check old assigned_to and status
        $oldTask = $this->taskService->find($id);
        if (!$oldTask) {
            return response()->json(['message' => 'Task not found'], 404);
        }
        $oldAssignedTo = $oldTask->assigned_to;
        $oldStatus = $oldTask->status;
        
        // Handle file uploads
        if ($request->hasFile('attachments')) {
            $data['attachments'] = $this->fileUploadService->uploadMultiple(
                $request->file('attachments'),
                'tasks'
            );
        }
        
        $requirementIds = $request->input('requirement_ids', []);
        $task = $this->taskService->update($id, $data, $request->user()->id);
        if (!$task) {
            return response()->json(['message' => 'Task not found'], 404);
        }
        
        // Sync requirements
        if ($request->has('requirement_ids')) {
            $task->requirements()->sync($requirementIds);
        }
        
        $task->load('project', 'assignedUser', 'creator');
        $user = $request->user();
        
        // Send notification if assigned user changed
        if (isset($data['assigned_to']) && $data['assigned_to'] != $oldAssignedTo && $data['assigned_to']) {
            $assignedUser = \App\Models\User::find($data['assigned_to']);
            if ($assignedUser) {
                $assignedUser->notify(new TaskAssignedNotification($task));
            }
        }
        
        // Notify about task update
        $usersToNotify = [];
        
        // Notify assigned user (if exists and different from updater)
        if ($task->assigned_to && $task->assigned_to !== $user->id) {
            $usersToNotify[] = $task->assigned_to;
        }
        
        // Notify admins
        $admins = \App\Models\User::where('role', 'Admin')->where('id', '!=', $user->id)->pluck('id')->toArray();
        $usersToNotify = array_merge($usersToNotify, $admins);
        
        // Notify creator if different from updater
        if ($task->created_by && $task->created_by !== $user->id) {
            $usersToNotify[] = $task->created_by;
        }
        
        $usersToNotify = array_unique($usersToNotify);
        foreach ($usersToNotify as $userId) {
            $notifyUser = \App\Models\User::find($userId);
            if ($notifyUser) {
                $notifyUser->notify(new TaskUpdatedNotification($task, $user));
            }
        }
        
        // Notify about status change if status changed
        if (isset($data['status']) && $data['status'] != $oldStatus) {
            foreach ($usersToNotify as $userId) {
                $notifyUser = \App\Models\User::find($userId);
                if ($notifyUser) {
                    $notifyUser->notify(new TaskStatusChangedNotification($task, $oldStatus, $data['status'], $user));
                }
            }
        }
        
        return new TaskResource($task->load('project', 'assignedUser', 'requirements'));
    }

    public function destroy(int $id, Request $request)
    {
        $user = $request->user();
        $task = $this->taskService->find($id);
        
        if (!$task) {
            return response()->json(['message' => 'Task not found'], 404);
        }

        // Only admin can delete tasks created by admin
        if ($task->created_by && $task->creator && $task->creator->role === 'Admin') {
            if ($user->role !== 'Admin') {
                return response()->json(['message' => 'Unauthorized: Only admin can delete tasks created by admin'], 403);
            }
        }

        // Developers cannot delete any tasks
        if ($user->role === 'Developer') {
            return response()->json(['message' => 'Unauthorized: Developers cannot delete tasks'], 403);
        }

        $taskTitle = $task->title;
        $assignedUserId = $task->assigned_to;
        $createdById = $task->created_by;
        
        $deleted = $this->taskService->delete($id, $user->id);
        if (!$deleted) {
            return response()->json(['message' => 'Failed to delete task'], 500);
        }
        
        // Notify assigned user and creator about deletion
        $usersToNotify = [];
        if ($assignedUserId && $assignedUserId !== $user->id) {
            $usersToNotify[] = $assignedUserId;
        }
        if ($createdById && $createdById !== $user->id) {
            $usersToNotify[] = $createdById;
        }
        
        // Notify admins
        $admins = \App\Models\User::where('role', 'Admin')->where('id', '!=', $user->id)->pluck('id')->toArray();
        $usersToNotify = array_merge($usersToNotify, $admins);
        $usersToNotify = array_unique($usersToNotify);
        
        foreach ($usersToNotify as $userId) {
            $notifyUser = \App\Models\User::find($userId);
            if ($notifyUser) {
                $notifyUser->notify(new TaskDeletedNotification($taskTitle, $user));
            }
        }
        
        return response()->json(['message' => 'Task deleted successfully']);
    }

    public function startTimer(Request $request, int $id)
    {
        $user = $request->user();
        $timer = $this->taskService->startTimer($id, $user->id);
        if (!$timer) {
            return response()->json(['message' => 'Task not found'], 404);
        }
        
        // Load task for notifications
        $task = \App\Models\Task::with('project', 'assignedUser', 'creator')->find($id);
        if ($task) {
            // Notify admin + assigned user (if different from starter)
            $usersToNotify = [];
            
            // Notify assigned user if different from starter
            if ($task->assigned_to && $task->assigned_to !== $user->id) {
                $usersToNotify[] = $task->assigned_to;
            }
            
            // Notify admins
            $admins = \App\Models\User::where('role', 'Admin')->pluck('id')->toArray();
            $usersToNotify = array_merge($usersToNotify, $admins);
            $usersToNotify = array_unique($usersToNotify);
            
            foreach ($usersToNotify as $userId) {
                $notifyUser = \App\Models\User::find($userId);
                if ($notifyUser) {
                    $notifyUser->notify(new TaskStartedNotification($task, $user));
                }
            }
        }
        
        return response()->json($timer);
    }

    public function pauseTimer(Request $request, int $timerId)
    {
        $timer = $this->taskService->pauseTimer($timerId);
        if (!$timer) {
            return response()->json(['message' => 'Timer not found or already paused/stopped'], 404);
        }
        return response()->json($timer);
    }

    public function resumeTimer(Request $request, int $timerId)
    {
        $timer = $this->taskService->resumeTimer($timerId);
        if (!$timer) {
            return response()->json(['message' => 'Timer not found or not paused'], 404);
        }
        return response()->json($timer);
    }

    public function stopTimer(Request $request, int $timerId)
    {
        $timer = $this->taskService->stopTimer($timerId);
        if (!$timer) {
            return response()->json(['message' => 'Timer not found'], 404);
        }
        return response()->json($timer);
    }

    public function getActiveTimer(Request $request, int $taskId)
    {
        $user = $request->user();
        $timer = \App\Models\TaskTimer::where('task_id', $taskId)
            ->where('user_id', $user->id)
            ->whereNull('stopped_at')
            ->latest()
            ->first();
        
        if (!$timer) {
            return response()->json(['timer' => null]);
        }
        
        return response()->json(['timer' => $timer]);
    }
}

