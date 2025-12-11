<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\Requirement;
use App\Models\ProjectDocument;
use Illuminate\Http\Request;

class ClientPortalController extends Controller
{
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
            ->orderBy('payment_date', 'desc')
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
            ->orderBy('payment_date', 'desc')
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
