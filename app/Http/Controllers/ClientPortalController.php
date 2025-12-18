<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\Requirement;
use App\Models\ProjectDocument;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ClientPortalController extends Controller
{
    protected $fileUploadService;

    public function __construct(FileUploadService $fileUploadService)
    {
        $this->fileUploadService = $fileUploadService;
    }
    /**
     * Get client's dashboard data
     */
    public function dashboard(Request $request)
    {
        $user = $request->user();
        
        // Find client by email
        $client = Client::where('email', $user->email)->first();
        
        if (!$client) {
            return response()->json(['message' => 'Client profile not found'], 404);
        }

        // Get all projects for this client
        $projects = Project::where('client_id', $client->id)
            ->with(['phases', 'teams.user', 'tasks.assignedUser'])
            ->get();

        // Get all tasks across all projects
        $projectIds = $projects->pluck('id');
        $tasks = \App\Models\Task::whereIn('project_id', $projectIds)
            ->with(['project', 'assignedUser'])
            ->get();

        // Get all payments
        $payments = ClientPayment::where('client_id', $client->id)
            ->with('project')
            ->orderBy('created_at', 'desc')
            ->get();

        // Get all requirements
        $requirements = Requirement::whereIn('project_id', $projectIds)
            ->with('project')
            ->get();

        // Get all documents
        $documents = ProjectDocument::whereIn('project_id', $projectIds)
            ->with('project')
            ->get();

        // Get developers assigned to projects (with logic to show "Developer" if same dev on multiple projects)
        $developers = $this->getProjectDevelopers($projects);

        return response()->json([
            'client' => $client,
            'projects' => $projects,
            'tasks' => $tasks,
            'payments' => $payments,
            'requirements' => $requirements,
            'documents' => $documents,
            'developers' => $developers,
        ]);
    }

    /**
     * Get client's projects
     */
    public function projects(Request $request)
    {
        $user = $request->user();
        $client = Client::where('email', $user->email)->first();
        
        if (!$client) {
            return response()->json(['message' => 'Client profile not found'], 404);
        }

        $projects = Project::where('client_id', $client->id)
            ->with(['phases', 'teams.user', 'tasks.assignedUser', 'requirements', 'documents'])
            ->get();

        return response()->json($projects);
    }

    /**
     * Get a specific project
     */
    public function project(Request $request, int $id)
    {
        $user = $request->user();
        $client = Client::where('email', $user->email)->first();
        
        if (!$client) {
            return response()->json(['message' => 'Client profile not found'], 404);
        }

        $project = Project::where('id', $id)
            ->where('client_id', $client->id)
            ->with(['phases', 'teams.user', 'tasks.assignedUser', 'requirements', 'documents'])
            ->first();

        if (!$project) {
            return response()->json(['message' => 'Project not found'], 404);
        }

        return response()->json($project);
    }

    /**
     * Get client's tasks
     */
    public function tasks(Request $request)
    {
        $user = $request->user();
        $client = Client::where('email', $user->email)->first();
        
        if (!$client) {
            return response()->json(['message' => 'Client profile not found'], 404);
        }

        $projectIds = Project::where('client_id', $client->id)->pluck('id');
        
        $tasks = \App\Models\Task::whereIn('project_id', $projectIds)
            ->with(['project', 'assignedUser'])
            ->get();

        return response()->json($tasks);
    }

    /**
     * Get client's payments
     */
    public function payments(Request $request)
    {
        $user = $request->user();
        $client = Client::where('email', $user->email)->first();
        
        if (!$client) {
            return response()->json(['message' => 'Client profile not found'], 404);
        }

        $payments = ClientPayment::where('client_id', $client->id)
            ->with('project')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($payments);
    }

    /**
     * Get client's requirements
     */
    public function requirements(Request $request)
    {
        $user = $request->user();
        $client = Client::where('email', $user->email)->first();
        
        if (!$client) {
            return response()->json(['message' => 'Client profile not found'], 404);
        }

        $projectIds = Project::where('client_id', $client->id)->pluck('id');
        
        $requirements = Requirement::whereIn('project_id', $projectIds)
            ->with('project')
            ->get();

        return response()->json($requirements);
    }

    /**
     * Get client's documents
     */
    public function documents(Request $request)
    {
        $user = $request->user();
        $client = Client::where('email', $user->email)->first();
        
        if (!$client) {
            return response()->json(['message' => 'Client profile not found'], 404);
        }

        $projectIds = Project::where('client_id', $client->id)->pluck('id');
        
        $documents = ProjectDocument::whereIn('project_id', $projectIds)
            ->with('project')
            ->get();

        return response()->json($documents);
    }

    /**
     * Create a requirement for client's project
     */
    public function createRequirement(Request $request)
    {
        $user = $request->user();
        $client = Client::where('email', $user->email)->first();
        
        if (!$client) {
            return response()->json(['message' => 'Client profile not found'], 404);
        }

        $request->validate([
            'project_id' => 'required|exists:projects,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:document,text',
            'document' => 'required_if:type,document|file|mimes:pdf,doc,docx,txt|max:10240',
            'priority' => 'nullable|in:Low,Medium,High,Critical',
        ]);

        // Verify the project belongs to this client
        $project = Project::where('id', $request->project_id)
            ->where('client_id', $client->id)
            ->first();

        if (!$project) {
            return response()->json(['message' => 'Project not found or access denied'], 404);
        }

        $data = [
            'project_id' => $request->project_id,
            'title' => $request->title,
            'description' => $request->description,
            'type' => $request->type,
            'priority' => $request->priority ?? 'Medium',
            'status' => 'Draft',
            'created_by' => $user->id,
        ];

        // Handle document upload
        if ($request->type === 'document' && $request->hasFile('document')) {
            try {
                $file = $request->file('document');
                
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
                Log::error('File upload error: ' . $e->getMessage());
                return response()->json([
                    'message' => 'File upload failed: ' . $e->getMessage()
                ], 500);
            }
        }

        try {
            $requirement = Requirement::create($data);
            $requirement->load(['project', 'creator']);
            
            // Notify admins + project team members
            $usersToNotify = [];
            
            // Notify admins
            $admins = \App\Models\User::where('role', 'Admin')->pluck('id')->toArray();
            $usersToNotify = array_merge($usersToNotify, $admins);
            
            // Notify team members assigned to project
            $project = $requirement->project;
            if ($project && $project->teams) {
                foreach ($project->teams as $team) {
                    if ($team->user_id) {
                        $usersToNotify[] = $team->user_id;
                    }
                }
            }
            
            $usersToNotify = array_unique($usersToNotify);
            foreach ($usersToNotify as $userId) {
                $notifyUser = \App\Models\User::find($userId);
                if ($notifyUser) {
                    $notifyUser->notify(new \App\Notifications\RequirementCreatedNotification($requirement, $user));
                }
            }
            
            return response()->json($requirement, 201);
        } catch (\Exception $e) {
            Log::error('Requirement creation error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to create requirement: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a document for client's project
     */
    public function createDocument(Request $request)
    {
        $user = $request->user();
        $client = Client::where('email', $user->email)->first();
        
        if (!$client) {
            return response()->json(['message' => 'Client profile not found'], 404);
        }

        $request->validate([
            'project_id' => 'required|exists:projects,id',
            'title' => 'required|string|max:255',
            'type' => 'required|in:Document,GitHub Credentials,Server Credentials,Database Credentials,API Keys,Domain Credentials,Hosting Credentials,Other',
            'description' => 'nullable|string',
            'url' => 'nullable|url|max:255',
            'notes' => 'nullable|string',
            'file' => 'nullable|file|max:10240', // 10MB max
        ]);

        // Verify the project belongs to this client
        $project = Project::where('id', $request->project_id)
            ->where('client_id', $client->id)
            ->first();

        if (!$project) {
            return response()->json(['message' => 'Project not found or access denied'], 404);
        }

        $data = [
            'project_id' => $request->project_id,
            'title' => $request->title,
            'type' => $request->type,
            'description' => $request->description,
            'url' => $request->url,
            'notes' => $request->notes,
            'uploaded_by' => $user->id,
        ];

        // Handle file upload
        if ($request->hasFile('file')) {
            try {
                $data['file_path'] = $this->fileUploadService->upload(
                    $request->file('file'),
                    'project-documents'
                );
            } catch (\Exception $e) {
                Log::error('File upload error: ' . $e->getMessage());
                return response()->json([
                    'message' => 'File upload failed: ' . $e->getMessage()
                ], 500);
            }
        }

        try {
            $document = ProjectDocument::create($data);
            return response()->json($document->load('uploader'), 201);
        } catch (\Exception $e) {
            Log::error('Document creation error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to create document: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get developers assigned to projects
     * If same developer works on multiple projects, show "Developer" instead of name
     */
    private function getProjectDevelopers($projects)
    {
        $allDevelopers = [];
        
        foreach ($projects as $project) {
            // Get developers from teams assigned to project
            // Team model represents a single team member, not a team with multiple members
            foreach ($project->teams as $team) {
                // Load the user relationship if not already loaded
                if (!$team->relationLoaded('user')) {
                    $team->load('user');
                }
                
                if ($team->user) {
                    $allDevelopers[] = [
                        'user_id' => $team->user->id,
                        'name' => $team->user->name,
                        'project_id' => $project->id,
                    ];
                }
            }
            
            // Get developers from tasks
            foreach ($project->tasks as $task) {
                if ($task->assignedUser) {
                    $allDevelopers[] = [
                        'user_id' => $task->assignedUser->id,
                        'name' => $task->assignedUser->name,
                        'project_id' => $project->id,
                    ];
                }
            }
        }

        // Count how many projects each developer works on
        $developerCounts = [];
        foreach ($allDevelopers as $dev) {
            if (!isset($developerCounts[$dev['user_id']])) {
                $developerCounts[$dev['user_id']] = [
                    'name' => $dev['name'],
                    'projects' => [],
                ];
            }
            if (!in_array($dev['project_id'], $developerCounts[$dev['user_id']]['projects'])) {
                $developerCounts[$dev['user_id']]['projects'][] = $dev['project_id'];
            }
        }

        // Format result: if developer works on multiple projects, show "Developer"
        $result = [];
        foreach ($projects as $project) {
            $projectDevelopers = [];
            foreach ($allDevelopers as $dev) {
                if ($dev['project_id'] === $project->id) {
                    $userId = $dev['user_id'];
                    $count = count($developerCounts[$userId]['projects']);
                    $projectDevelopers[] = [
                        'user_id' => $userId,
                        'name' => $count > 1 ? 'Developer' : $dev['name'],
                        'projects_count' => $count,
                    ];
                }
            }
            // Remove duplicates based on user_id
            $uniqueDevelopers = [];
            $seenUserIds = [];
            foreach ($projectDevelopers as $dev) {
                if (!in_array($dev['user_id'], $seenUserIds)) {
                    $uniqueDevelopers[] = $dev;
                    $seenUserIds[] = $dev['user_id'];
                }
            }
            $result[$project->id] = $uniqueDevelopers;
        }

        return $result;
    }
}
