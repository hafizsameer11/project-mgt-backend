<?php

namespace App\Http\Controllers;

use App\Models\Requirement;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RequirementController extends Controller
{
    protected $fileUploadService;

    public function __construct(FileUploadService $fileUploadService)
    {
        $this->fileUploadService = $fileUploadService;
    }

    public function index(Request $request)
    {
        $query = Requirement::with(['project', 'creator']);

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
            $file = $request->file('document');
            $path = $this->fileUploadService->upload($file, 'requirements');
            $data['document_path'] = $path;
            $data['document_name'] = $file->getClientOriginalName();
            $data['document_type'] = $file->getClientOriginalExtension();
        }

        $requirement = Requirement::create($data);
        return response()->json($requirement->load(['project', 'creator']), 201);
    }

    public function show(int $id)
    {
        $requirement = Requirement::with(['project', 'creator'])->find($id);
        if (!$requirement) {
            return response()->json(['message' => 'Requirement not found'], 404);
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
            // Delete old document
            if ($requirement->document_path) {
                Storage::disk('public')->delete($requirement->document_path);
            }
            
            $file = $request->file('document');
            $path = $this->fileUploadService->upload($file, 'requirements');
            $data['document_path'] = $path;
            $data['document_name'] = $file->getClientOriginalName();
            $data['document_type'] = $file->getClientOriginalExtension();
        }

        $requirement->update($data);
        return response()->json($requirement->load(['project', 'creator']));
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
