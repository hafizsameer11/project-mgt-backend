<?php

namespace App\Http\Controllers;

use App\Models\Requirement;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class RequirementController extends Controller
{
    protected $fileUploadService;

    public function __construct(FileUploadService $fileUploadService)
    {
        $this->fileUploadService = $fileUploadService;
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $query = Requirement::with(['project', 'creator']);

        // Role-based filtering
        if ($user && $user->role === 'Developer') {
            // Developers see requirements for projects they are assigned to
            $projectIds = collect();
            
            // Get projects via team assignments
            $team = \App\Models\Team::where('user_id', $user->id)->first();
            if ($team) {
                $teamProjectIds = $team->projects()->pluck('projects.id');
                $projectIds = $projectIds->merge($teamProjectIds);
            }
            
            // Get projects via task assignments
            $taskProjectIds = \App\Models\Task::where('assigned_to', $user->id)
                ->whereNotNull('project_id')
                ->distinct()
                ->pluck('project_id');
            $projectIds = $projectIds->merge($taskProjectIds);
            
            if ($projectIds->isNotEmpty()) {
                $query->whereIn('project_id', $projectIds->unique());
            } else {
                // If no projects found, show empty result
                $query->whereRaw('1 = 0');
            }
        } elseif ($user && $user->role === 'Project Manager') {
            // Project Managers see requirements for projects they manage
            $team = \App\Models\Team::where('user_id', $user->id)
                ->where('role', 'Project Manager')
                ->first();
            
            if ($team) {
                $projectIds = $team->projects()->pluck('projects.id');
                $query->whereIn('project_id', $projectIds);
            } else {
                $query->whereRaw('1 = 0');
            }
        }
        // Admin sees all requirements (no additional filtering)

        if ($request->has('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $requirements = $query->orderBy('created_at', 'desc')->paginate(15);
        return response()->json($requirements);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        
        // Only Admin and Project Manager can create requirements
        if (!in_array($user->role, ['Admin', 'Project Manager'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'project_id' => 'required|exists:projects,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:document,text',
            'document' => 'required_if:type,document|file|mimes:pdf,doc,docx,txt|max:10240',
            'priority' => 'nullable|in:Low,Medium,High,Critical',
            'status' => 'nullable|in:Draft,Active,Completed,Cancelled',
        ]);

        $data = [
            'project_id' => $request->project_id,
            'title' => $request->title,
            'description' => $request->description,
            'type' => $request->type,
            'priority' => $request->priority ?? 'Medium',
            'status' => $request->status ?? 'Draft',
            'created_by' => $user->id,
        ];

        // Handle document upload
        if ($request->type === 'document' && $request->hasFile('document')) {
            try {
                $file = $request->file('document');
                
                // Check if file upload was successful
                if (!$file->isValid()) {
                    return response()->json([
                        'message' => 'File upload failed: ' . $file->getErrorMessage()
                    ], 422);
                }
                
                $path = $this->fileUploadService->upload($file, 'requirements');
                $data['document_path'] = $path;
                $data['document_name'] = $file->getClientOriginalName();
                $data['document_type'] = $file->getClientOriginalExtension();
            } catch (\Exception $e) {
                Log::error('File upload error: ' . $e->getMessage(), [
                    'trace' => $e->getTraceAsString()
                ]);
                return response()->json([
                    'message' => 'File upload failed: ' . $e->getMessage()
                ], 500);
            }
        }

        try {
            $requirement = Requirement::create($data);
            return response()->json($requirement->load(['project', 'creator']), 201);
        } catch (\Exception $e) {
            Log::error('Requirement creation error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'data' => $data
            ]);
            return response()->json([
                'message' => 'Failed to create requirement: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show(Request $request, int $id)
    {
        $requirement = Requirement::with(['project', 'creator'])->find($id);
        if (!$requirement) {
            return response()->json(['message' => 'Requirement not found'], 404);
        }

        $user = $request->user();
        
        // Access control for developers
        if ($user && $user->role === 'Developer') {
            $projectId = $requirement->project_id;
            $hasAccess = false;
            
            // Check if developer is assigned via team
            $team = \App\Models\Team::where('user_id', $user->id)->first();
            if ($team) {
                $hasAccess = $team->projects()->where('projects.id', $projectId)->exists();
            }
            
            // Check if developer has tasks assigned for this project
            if (!$hasAccess) {
                $hasAccess = \App\Models\Task::where('assigned_to', $user->id)
                    ->where('project_id', $projectId)
                    ->exists();
            }
            
            if (!$hasAccess) {
                return response()->json(['message' => 'You do not have access to this requirement'], 403);
            }
        }
        
        // Access control for Project Managers
        if ($user && $user->role === 'Project Manager') {
            $projectId = $requirement->project_id;
            $team = \App\Models\Team::where('user_id', $user->id)
                ->where('role', 'Project Manager')
                ->first();
            
            if ($team) {
                $hasAccess = $team->projects()->where('projects.id', $projectId)->exists();
                if (!$hasAccess) {
                    return response()->json(['message' => 'You do not have access to this requirement'], 403);
                }
            } else {
                return response()->json(['message' => 'You do not have access to this requirement'], 403);
            }
        }

        return response()->json($requirement);
    }

    public function update(Request $request, int $id)
    {
        $user = $request->user();
        $requirement = Requirement::find($id);
        
        if (!$requirement) {
            return response()->json(['message' => 'Requirement not found'], 404);
        }

        // Only Admin and Project Manager can update requirements
        if (!in_array($user->role, ['Admin', 'Project Manager'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'sometimes|in:document,text',
            'document' => 'nullable|file|mimes:pdf,doc,docx,txt|max:10240',
            'priority' => 'nullable|in:Low,Medium,High,Critical',
            'status' => 'nullable|in:Draft,Active,Completed,Cancelled',
        ]);

        $data = $request->only(['title', 'description', 'type', 'priority', 'status']);

        // Handle document upload
        if ($request->hasFile('document')) {
            try {
                // Delete old document
                if ($requirement->document_path) {
                    Storage::disk('public')->delete($requirement->document_path);
                }
                
                $file = $request->file('document');
                
                // Check if file upload was successful
                if (!$file->isValid()) {
                    return response()->json([
                        'message' => 'File upload failed: ' . $file->getErrorMessage()
                    ], 422);
                }
                
                $path = $this->fileUploadService->upload($file, 'requirements');
                $data['document_path'] = $path;
                $data['document_name'] = $file->getClientOriginalName();
                $data['document_type'] = $file->getClientOriginalExtension();
            } catch (\Exception $e) {
                Log::error('File upload error: ' . $e->getMessage(), [
                    'trace' => $e->getTraceAsString()
                ]);
                return response()->json([
                    'message' => 'File upload failed: ' . $e->getMessage()
                ], 500);
            }
        }

        try {
            $requirement->update($data);
            return response()->json($requirement->load(['project', 'creator']));
        } catch (\Exception $e) {
            Log::error('Requirement update error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Failed to update requirement: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy(int $id, Request $request)
    {
        $user = $request->user();
        $requirement = Requirement::find($id);
        
        if (!$requirement) {
            return response()->json(['message' => 'Requirement not found'], 404);
        }

        // Only Admin and Project Manager can delete requirements
        if (!in_array($user->role, ['Admin', 'Project Manager'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Delete document if exists
        if ($requirement->document_path) {
            Storage::disk('public')->delete($requirement->document_path);
        }

        $requirement->delete();
        return response()->json(['message' => 'Requirement deleted successfully']);
    }

    public function download(int $id)
    {
        $requirement = Requirement::find($id);
        
        if (!$requirement || !$requirement->document_path) {
            return response()->json(['message' => 'Document not found'], 404);
        }

        $path = storage_path('app/public/' . $requirement->document_path);
        
        if (!file_exists($path)) {
            return response()->json(['message' => 'File not found'], 404);
        }

        return response()->download($path, $requirement->document_name);
    }
}
