<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Services\ProjectService;
use App\Services\FileUploadService;
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
        $projects = $this->projectService->getAll($request->all());
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
        
        $project = $this->projectService->create($data, $request->user()->id);
        return new ProjectResource($project);
    }

    public function show(int $id)
    {
        $project = \App\Models\Project::with('teams', 'client', 'assignedBd')->find($id);
        if (!$project) {
            return response()->json(['message' => 'Project not found'], 404);
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
        
        $project = $this->projectService->update($id, $data, $request->user()->id);
        if (!$project) {
            return response()->json(['message' => 'Project not found'], 404);
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

