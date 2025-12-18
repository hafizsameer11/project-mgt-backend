<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Services\ProjectService;
use App\Services\FileUploadService;
use App\Notifications\ProjectCreatedNotification;
use App\Notifications\ProjectUpdatedNotification;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    protected $projectService;
    protected $fileUploadService;

    public function __construct(ProjectService $projectService, FileUploadService $fileUploadService)
    {
        $this->projectService = $projectService;
        $this->fileUploadService = $fileUploadService;
    }

    public function index(Request $request)
    {
        $projects = $this->projectService->getAll($request->all(), $request->user());
        return ProjectResource::collection($projects);
    }

    public function store(StoreProjectRequest $request)
    {
        $data = $request->validated();
        
        // Handle file uploads
        if ($request->hasFile('attachments')) {
            $data['attachments'] = $this->fileUploadService->uploadMultiple(
                $request->file('attachments'),
                'projects'
            );
        }
        
        $user = $request->user();
        $project = $this->projectService->create($data, $user->id);
        $project->load('teams', 'client');
        
        // Notify admins + project team members
        $usersToNotify = [];
        
        // Notify admins
        $admins = \App\Models\User::where('role', 'Admin')->where('id', '!=', $user->id)->pluck('id')->toArray();
        $usersToNotify = array_merge($usersToNotify, $admins);
        
        // Notify team members assigned to project
        if ($project->teams) {
            foreach ($project->teams as $team) {
                if ($team->user_id && $team->user_id !== $user->id) {
                    $usersToNotify[] = $team->user_id;
                }
            }
        }
        
        $usersToNotify = array_unique($usersToNotify);
        foreach ($usersToNotify as $userId) {
            $notifyUser = \App\Models\User::find($userId);
            if ($notifyUser) {
                $notifyUser->notify(new ProjectCreatedNotification($project, $user));
            }
        }
        
        return new ProjectResource($project);
    }

    public function show(Request $request, int $id)
    {
        $project = \App\Models\Project::with('teams', 'client', 'assignedBd')->find($id);
        if (!$project) {
            return response()->json(['message' => 'Project not found'], 404);
        }

        $user = $request->user();
        
        // Access control for developers
        if ($user && $user->role === 'Developer') {
            $hasAccess = false;
            
            // Check if developer is assigned via team
            $team = \App\Models\Team::where('user_id', $user->id)->first();
            if ($team) {
                $hasAccess = $team->projects()->where('projects.id', $id)->exists();
            }
            
            // Check if developer has tasks assigned for this project
            if (!$hasAccess) {
                $hasAccess = \App\Models\Task::where('assigned_to', $user->id)
                    ->where('project_id', $id)
                    ->exists();
            }
            
            if (!$hasAccess) {
                return response()->json(['message' => 'You do not have access to this project'], 403);
            }
        }
        
        // Access control for Project Managers
        if ($user && $user->role === 'Project Manager') {
            $team = \App\Models\Team::where('user_id', $user->id)
                ->where('role', 'Project Manager')
                ->first();
            
            if ($team) {
                $hasAccess = $team->projects()->where('projects.id', $id)->exists();
                if (!$hasAccess) {
                    return response()->json(['message' => 'You do not have access to this project'], 403);
                }
            } else {
                return response()->json(['message' => 'You do not have access to this project'], 403);
            }
        }

        return new ProjectResource($project);
    }

    public function update(UpdateProjectRequest $request, int $id)
    {
        $data = $request->validated();
        
        // Handle file uploads
        if ($request->hasFile('attachments')) {
            $data['attachments'] = $this->fileUploadService->uploadMultiple(
                $request->file('attachments'),
                'projects'
            );
        }
        
        $user = $request->user();
        $project = $this->projectService->update($id, $data, $user->id);
        if (!$project) {
            return response()->json(['message' => 'Project not found'], 404);
        }
        
        $project->load('teams', 'client');
        
        // Notify admins + project team members
        $usersToNotify = [];
        
        // Notify admins
        $admins = \App\Models\User::where('role', 'Admin')->where('id', '!=', $user->id)->pluck('id')->toArray();
        $usersToNotify = array_merge($usersToNotify, $admins);
        
        // Notify team members assigned to project
        if ($project->teams) {
            foreach ($project->teams as $team) {
                if ($team->user_id && $team->user_id !== $user->id) {
                    $usersToNotify[] = $team->user_id;
                }
            }
        }
        
        $usersToNotify = array_unique($usersToNotify);
        foreach ($usersToNotify as $userId) {
            $notifyUser = \App\Models\User::find($userId);
            if ($notifyUser) {
                $notifyUser->notify(new ProjectUpdatedNotification($project, $user));
            }
        }
        
        return new ProjectResource($project);
    }

    public function destroy(int $id, Request $request)
    {
        $deleted = $this->projectService->delete($id, $request->user()->id);
        if (!$deleted) {
            return response()->json(['message' => 'Project not found'], 404);
        }
        return response()->json(['message' => 'Project deleted successfully']);
    }
}

