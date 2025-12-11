<?php

namespace App\Http\Controllers;

use App\Models\ProjectDocument;
use App\Services\FileUploadService;
use Illuminate\Http\Request;

class ProjectDocumentController extends Controller
{
    protected $fileUploadService;

    public function __construct(FileUploadService $fileUploadService)
    {
        $this->fileUploadService = $fileUploadService;
    }

    public function index(Request $request)
    {
        $request->validate([
            'project_id' => 'required|exists:projects,id',
        ]);

        $documents = ProjectDocument::where('project_id', $request->project_id)
            ->with('uploader')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($documents);
    }

    public function store(Request $request)
    {
        $request->validate([
            'project_id' => 'required|exists:projects,id',
            'title' => 'required|string|max:255',
            'type' => 'required|in:Document,GitHub Credentials,Server Credentials,Database Credentials,API Keys,Domain Credentials,Hosting Credentials,Other',
            'description' => 'nullable|string',
            'url' => 'nullable|url|max:255',
            'notes' => 'nullable|string',
            'credentials' => 'nullable|array',
            'file' => 'nullable|file|max:10240', // 10MB max
        ]);

        $data = [
            'project_id' => $request->project_id,
            'title' => $request->title,
            'type' => $request->type,
            'description' => $request->description,
            'url' => $request->url,
            'notes' => $request->notes,
            'uploaded_by' => $request->user()->id,
        ];

        // Handle file upload
        if ($request->hasFile('file')) {
            $data['file_path'] = $this->fileUploadService->upload(
                $request->file('file'),
                'project-documents'
            );
        }

        // Handle credentials (will be encrypted by model)
        if ($request->has('credentials')) {
            $data['credentials'] = $request->credentials;
        }

        $document = ProjectDocument::create($data);

        return response()->json($document->load('uploader'), 201);
    }

    public function show(int $id)
    {
        $document = ProjectDocument::with('project', 'uploader')->find($id);
        if (!$document) {
            return response()->json(['message' => 'Document not found'], 404);
        }
        return response()->json($document);
    }

    public function update(Request $request, int $id)
    {
        $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'type' => 'sometimes|in:Document,GitHub Credentials,Server Credentials,Database Credentials,API Keys,Domain Credentials,Hosting Credentials,Other',
            'description' => 'nullable|string',
            'url' => 'nullable|url|max:255',
            'notes' => 'nullable|string',
            'credentials' => 'nullable|array',
            'file' => 'nullable|file|max:10240',
        ]);

        $document = ProjectDocument::find($id);
        if (!$document) {
            return response()->json(['message' => 'Document not found'], 404);
        }

        $data = $request->only(['title', 'type', 'description', 'url', 'notes']);

        // Handle file upload
        if ($request->hasFile('file')) {
            // Delete old file if exists
            if ($document->file_path) {
                $this->fileUploadService->delete($document->file_path);
            }
            $data['file_path'] = $this->fileUploadService->upload(
                $request->file('file'),
                'project-documents'
            );
        }

        // Handle credentials
        if ($request->has('credentials')) {
            $data['credentials'] = $request->credentials;
        }

        $document->update($data);

        return response()->json($document->load('uploader'));
    }

    public function destroy(int $id)
    {
        $document = ProjectDocument::find($id);
        if (!$document) {
            return response()->json(['message' => 'Document not found'], 404);
        }

        // Delete file if exists
        if ($document->file_path) {
            $this->fileUploadService->delete($document->file_path);
        }

        $document->delete();

        return response()->json(['message' => 'Document deleted successfully']);
    }

    public function download(int $id)
    {
        $document = ProjectDocument::find($id);
        if (!$document || !$document->file_path) {
            return response()->json(['message' => 'File not found'], 404);
        }

        $filePath = storage_path('app/public/' . str_replace('/storage/', '', $document->file_path));
        
        if (!file_exists($filePath)) {
            return response()->json(['message' => 'File not found'], 404);
        }

        return response()->download($filePath, $document->title);
    }
}

