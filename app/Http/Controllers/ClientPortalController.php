<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\Requirement;
use App\Models\ProjectDocument;
use App\Models\ProjectTeamClientAlias;
use App\Models\Task;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

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

        // Apply aliases to task assigned users
        foreach ($project->tasks as $task) {
            if ($task->assignedUser) {
                $team = \App\Models\Team::where('user_id', $task->assignedUser->id)->first();
                if ($team) {
                    $displayName = ProjectTeamClientAlias::getDisplayName(
                        $client->id,
                        $team->id,
                        $project->id
                    );
                    $task->assigned_user_display_name = $displayName;
                }
            }
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

        // Apply aliases to task assigned users
        foreach ($tasks as $task) {
            if ($task->assignedUser) {
                $team = \App\Models\Team::where('user_id', $task->assignedUser->id)->first();
                if ($team) {
                    $displayName = ProjectTeamClientAlias::getDisplayName(
                        $client->id,
                        $team->id,
                        $task->project_id
                    );
                    // Add display_name to the task object
                    $task->assigned_user_display_name = $displayName;
                }
            }
        }

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
     * Create a project (client can create their own projects)
     */
    public function createProject(Request $request)
    {
        $user = $request->user();
        $client = Client::where('email', $user->email)->first();
        
        if (!$client) {
            return response()->json(['message' => 'Client profile not found'], 404);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'budget' => 'nullable|numeric|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'project_type' => 'nullable|string',
            'priority' => 'nullable|in:Low,Medium,High,Critical',
            'tags' => 'nullable|array',
        ]);

        $validated['client_id'] = $client->id;
        $validated['status'] = 'Planning';

        $project = Project::create($validated);
        return response()->json($project->load('client', 'phases', 'teams.user'), 201);
    }

    /**
     * Update a project (client can update their own projects)
     */
    public function updateProject(Request $request, int $id)
    {
        $user = $request->user();
        $client = Client::where('email', $user->email)->first();
        
        if (!$client) {
            return response()->json(['message' => 'Client profile not found'], 404);
        }

        $project = Project::where('id', $id)
            ->where('client_id', $client->id)
            ->first();

        if (!$project) {
            return response()->json(['message' => 'Project not found or access denied'], 404);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'budget' => 'nullable|numeric|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'project_type' => 'nullable|string',
            'priority' => 'nullable|in:Low,Medium,High,Critical',
            'status' => 'nullable|in:Planning,In Progress,On Hold,Completed,Cancelled',
            'tags' => 'nullable|array',
        ]);

        $project->update($validated);
        return response()->json($project->load('client', 'phases', 'teams.user', 'tasks.assignedUser', 'requirements', 'documents'));
    }

    /**
     * Create a task for client's project
     */
    public function createTask(Request $request)
    {
        $user = $request->user();
        $client = Client::where('email', $user->email)->first();
        
        if (!$client) {
            return response()->json(['message' => 'Client profile not found'], 404);
        }

        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'nullable|in:Low,Medium,High,Critical',
            'due_date' => 'nullable|date',
            'estimated_hours' => 'nullable|numeric|min:0',
        ]);

        // Verify the project belongs to this client
        $project = Project::where('id', $validated['project_id'])
            ->where('client_id', $client->id)
            ->first();

        if (!$project) {
            return response()->json(['message' => 'Project not found or access denied'], 404);
        }

        $validated['status'] = 'Pending';
        $validated['created_by'] = $user->id;

        $task = Task::create($validated);
        
        // Notify admins and project team
        $this->notifyTaskCreated($task, $user);
        
        return response()->json($task->load('project', 'assignedUser', 'creator'), 201);
    }

    /**
     * Update a task
     */
    public function updateTask(Request $request, int $id)
    {
        $user = $request->user();
        $client = Client::where('email', $user->email)->first();
        
        if (!$client) {
            return response()->json(['message' => 'Client profile not found'], 404);
        }

        $task = Task::where('id', $id)
            ->whereHas('project', function($q) use ($client) {
                $q->where('client_id', $client->id);
            })
            ->first();

        if (!$task) {
            return response()->json(['message' => 'Task not found or access denied'], 404);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'nullable|in:Low,Medium,High,Critical',
            'status' => 'nullable|in:Pending,In Progress,Completed,Review,On Hold',
            'due_date' => 'nullable|date',
            'estimated_hours' => 'nullable|numeric|min:0',
        ]);

        $oldStatus = $task->status;
        $task->update($validated);

        // Notify on status change
        if (isset($validated['status']) && $validated['status'] !== $oldStatus) {
            $this->notifyTaskStatusChanged($task, $user, $oldStatus);
        } else {
            $this->notifyTaskUpdated($task, $user);
        }

        return response()->json($task->load('project', 'assignedUser', 'creator'));
    }

    /**
     * Get developers assigned to projects with client aliases
     */
    private function getProjectDevelopers($projects)
    {
        $client = null;
        if ($projects->isNotEmpty()) {
            $firstProject = $projects->first();
            $client = $firstProject->client;
        }

        if (!$client) {
            return [];
        }

        $allDevelopers = [];
        
        foreach ($projects as $project) {
            // Get developers from teams assigned to project
            foreach ($project->teams as $team) {
                if (!$team->relationLoaded('user')) {
                    $team->load('user');
                }
                
                if ($team->user || $team->full_name) {
                    $displayName = ProjectTeamClientAlias::getDisplayName(
                        $client->id,
                        $team->id,
                        $project->id
                    );
                    
                    $allDevelopers[] = [
                        'team_id' => $team->id,
                        'user_id' => $team->user_id,
                        'name' => $displayName,
                        'project_id' => $project->id,
                    ];
                }
            }
            
            // Get developers from tasks
            foreach ($project->tasks as $task) {
                if ($task->assignedUser) {
                    // For tasks, we need to find the team member by user_id
                    $team = \App\Models\Team::where('user_id', $task->assignedUser->id)->first();
                    if ($team) {
                        $displayName = ProjectTeamClientAlias::getDisplayName(
                            $client->id,
                            $team->id,
                            $project->id
                        );
                        
                        $allDevelopers[] = [
                            'team_id' => $team->id,
                            'user_id' => $task->assignedUser->id,
                            'name' => $displayName,
                            'project_id' => $project->id,
                        ];
                    } else {
                        // Fallback if no team record exists
                        $allDevelopers[] = [
                            'team_id' => null,
                            'user_id' => $task->assignedUser->id,
                            'name' => $task->assignedUser->name,
                            'project_id' => $project->id,
                        ];
                    }
                }
            }
        }

        // Format result by project
        $result = [];
        foreach ($projects as $project) {
            $projectDevelopers = [];
            $seenTeamIds = [];
            
            foreach ($allDevelopers as $dev) {
                if ($dev['project_id'] === $project->id && !in_array($dev['team_id'], $seenTeamIds)) {
                    $projectDevelopers[] = [
                        'team_id' => $dev['team_id'],
                        'user_id' => $dev['user_id'],
                        'name' => $dev['name'],
                    ];
                    if ($dev['team_id']) {
                        $seenTeamIds[] = $dev['team_id'];
                    }
                }
            }
            
            $result[$project->id] = $projectDevelopers;
        }

        return $result;
    }

    private function notifyTaskCreated($task, $user)
    {
        // Notify admins + project team members
        $usersToNotify = [];
        $admins = \App\Models\User::where('role', 'Admin')->pluck('id')->toArray();
        $usersToNotify = array_merge($usersToNotify, $admins);
        
        if ($task->project && $task->project->teams) {
            foreach ($task->project->teams as $team) {
                if ($team->user_id) {
                    $usersToNotify[] = $team->user_id;
                }
            }
        }
        
        $usersToNotify = array_unique($usersToNotify);
        foreach ($usersToNotify as $userId) {
            $notifyUser = \App\Models\User::find($userId);
            if ($notifyUser) {
                $notifyUser->notify(new \App\Notifications\TaskCreatedNotification($task, $user));
            }
        }
    }

    private function notifyTaskUpdated($task, $user)
    {
        $usersToNotify = [];
        $admins = \App\Models\User::where('role', 'Admin')->pluck('id')->toArray();
        $usersToNotify = array_merge($usersToNotify, $admins);
        
        if ($task->assignedUser) {
            $usersToNotify[] = $task->assignedUser->id;
        }
        
        if ($task->project && $task->project->teams) {
            foreach ($task->project->teams as $team) {
                if ($team->user_id) {
                    $usersToNotify[] = $team->user_id;
                }
            }
        }
        
        $usersToNotify = array_unique($usersToNotify);
        foreach ($usersToNotify as $userId) {
            $notifyUser = \App\Models\User::find($userId);
            if ($notifyUser) {
                $notifyUser->notify(new \App\Notifications\TaskUpdatedNotification($task, $user));
            }
        }
    }

    private function notifyTaskStatusChanged($task, $user, $oldStatus)
    {
        $usersToNotify = [];
        $admins = \App\Models\User::where('role', 'Admin')->pluck('id')->toArray();
        $usersToNotify = array_merge($usersToNotify, $admins);
        
        if ($task->assignedUser) {
            $usersToNotify[] = $task->assignedUser->id;
        }
        
        if ($task->project && $task->project->teams) {
            foreach ($task->project->teams as $team) {
                if ($team->user_id) {
                    $usersToNotify[] = $team->user_id;
                }
            }
        }
        
        $usersToNotify = array_unique($usersToNotify);
        foreach ($usersToNotify as $userId) {
            $notifyUser = \App\Models\User::find($userId);
            if ($notifyUser) {
                $notifyUser->notify(new \App\Notifications\TaskStatusChangedNotification($task, $user, $oldStatus));
            }
        }
    }
}
