<?php

namespace App\Http\Controllers;

use App\Models\ProjectTeamClientAlias;
use App\Models\Client;
use App\Models\Team;
use Illuminate\Http\Request;

class TeamMemberAliasController extends Controller
{
    /**
     * Get all aliases for a client
     */
    public function index(Request $request)
    {
        $query = ProjectTeamClientAlias::with('client', 'project', 'team.user');

        if ($request->has('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        if ($request->has('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        if ($request->has('team_id')) {
            $query->where('team_id', $request->team_id);
        }

        $aliases = $query->get();
        return response()->json($aliases);
    }

    /**
     * Store a new alias
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'project_id' => 'nullable|exists:projects,id',
            'team_id' => 'required|exists:teams,id',
            'display_name' => 'required|string|max:255',
            'notes' => 'nullable|string',
        ]);

        // Check if alias already exists
        $existing = ProjectTeamClientAlias::where('client_id', $validated['client_id'])
            ->where('project_id', $validated['project_id'] ?? null)
            ->where('team_id', $validated['team_id'])
            ->first();

        if ($existing) {
            $existing->update($validated);
            return response()->json($existing->load('client', 'project', 'team.user'));
        }

        $alias = ProjectTeamClientAlias::create($validated);
        return response()->json($alias->load('client', 'project', 'team.user'), 201);
    }

    /**
     * Update an alias
     */
    public function update(Request $request, int $id)
    {
        $alias = ProjectTeamClientAlias::find($id);
        if (!$alias) {
            return response()->json(['message' => 'Alias not found'], 404);
        }

        $validated = $request->validate([
            'display_name' => 'sometimes|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $alias->update($validated);
        return response()->json($alias->load('client', 'project', 'team.user'));
    }

    /**
     * Delete an alias
     */
    public function destroy(int $id)
    {
        $alias = ProjectTeamClientAlias::find($id);
        if (!$alias) {
            return response()->json(['message' => 'Alias not found'], 404);
        }

        $alias->delete();
        return response()->json(['message' => 'Alias deleted successfully']);
    }

    /**
     * Get teams available for a client
     */
    public function getAvailableTeams(Request $request, int $clientId)
    {
        $projectId = $request->get('project_id');

        if ($projectId) {
            // Get teams assigned to this project
            $project = \App\Models\Project::find($projectId);
            if (!$project || $project->client_id != $clientId) {
                return response()->json(['message' => 'Project not found or access denied'], 404);
            }
            $teams = $project->teams()->with('user')->get();
        } else {
            // Get all teams
            $teams = Team::with('user')->get();
        }

        return response()->json($teams);
    }
}

