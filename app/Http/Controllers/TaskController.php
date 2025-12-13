<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Services\TaskService;
use App\Services\FileUploadService;
use App\Notifications\TaskAssignedNotification;
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
        
        // Send notification to assigned user if different from creator
        if ($task->assigned_to && $task->assigned_to !== $user->id) {
            $assignedUser = \App\Models\User::find($task->assigned_to);
            if ($assignedUser) {
                $assignedUser->notify(new TaskAssignedNotification($task->load('project')));
            }
        }
        
        return new TaskResource($task->load('project', 'assignedUser', 'creator', 'requirements'));
    }

    public function show(int $id)
    {
        $task = $this->taskService->find($id);
        if (!$task) {
            return response()->json(['message' => 'Task not found'], 404);
        }
        return new TaskResource($task->load('project', 'assignedUser', 'creator', 'timers', 'requirements'));
    }

    public function update(UpdateTaskRequest $request, int $id)
    {
        $data = $request->validated();
        
        // Get task before update to check old assigned_to
        $oldTask = $this->taskService->find($id);
        if (!$oldTask) {
            return response()->json(['message' => 'Task not found'], 404);
        }
        $oldAssignedTo = $oldTask->assigned_to;
        
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
        
        // Send notification if assigned user changed
        if (isset($data['assigned_to']) && $data['assigned_to'] != $oldAssignedTo && $data['assigned_to']) {
            $assignedUser = \App\Models\User::find($data['assigned_to']);
            if ($assignedUser) {
                $assignedUser->notify(new TaskAssignedNotification($task->load('project')));
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

        $deleted = $this->taskService->delete($id, $user->id);
        if (!$deleted) {
            return response()->json(['message' => 'Failed to delete task'], 500);
        }
        return response()->json(['message' => 'Task deleted successfully']);
    }

    public function startTimer(Request $request, int $id)
    {
        $timer = $this->taskService->startTimer($id, $request->user()->id);
        if (!$timer) {
            return response()->json(['message' => 'Task not found'], 404);
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

