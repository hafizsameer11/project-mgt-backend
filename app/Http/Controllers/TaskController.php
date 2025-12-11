<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Services\TaskService;
use App\Services\FileUploadService;
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
        $tasks = $this->taskService->getAll($request->all());
        return TaskResource::collection($tasks);
    }

    public function store(StoreTaskRequest $request)
    {
        $data = $request->validated();
        
        // Set created_by if not provided (allows developers to create their own tasks)
        if (!isset($data['created_by'])) {
            $data['created_by'] = $request->user()->id;
        }
        
        // Handle file uploads
        if ($request->hasFile('attachments')) {
            $data['attachments'] = $this->fileUploadService->uploadMultiple(
                $request->file('attachments'),
                'tasks'
            );
        }
        
        $task = $this->taskService->create($data, $request->user()->id);
        return new TaskResource($task->load('project', 'assignedUser', 'creator'));
    }

    public function show(int $id)
    {
        $task = $this->taskService->taskRepository->find($id);
        if (!$task) {
            return response()->json(['message' => 'Task not found'], 404);
        }
        return new TaskResource($task->load('project', 'assignedUser', 'timers'));
    }

    public function update(UpdateTaskRequest $request, int $id)
    {
        $data = $request->validated();
        
        // Handle file uploads
        if ($request->hasFile('attachments')) {
            $data['attachments'] = $this->fileUploadService->uploadMultiple(
                $request->file('attachments'),
                'tasks'
            );
        }
        
        $task = $this->taskService->update($id, $data, $request->user()->id);
        if (!$task) {
            return response()->json(['message' => 'Task not found'], 404);
        }
        return new TaskResource($task->load('project', 'assignedUser'));
    }

    public function destroy(int $id, Request $request)
    {
        $deleted = $this->taskService->delete($id, $request->user()->id);
        if (!$deleted) {
            return response()->json(['message' => 'Task not found'], 404);
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

    public function stopTimer(Request $request, int $timerId)
    {
        $timer = $this->taskService->stopTimer($timerId);
        if (!$timer) {
            return response()->json(['message' => 'Timer not found'], 404);
        }
        return response()->json($timer);
    }
}

