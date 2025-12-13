<?php

namespace App\Http\Controllers;

use App\Http\Resources\TeamResource;
use App\Repositories\TeamRepository;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    protected $teamRepository;

    public function __construct(TeamRepository $teamRepository)
    {
        $this->teamRepository = $teamRepository;
    }

    public function index(Request $request)
    {
        $query = \App\Models\Team::query();

        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        if ($request->has('payment_type')) {
            $query->where('payment_type', $request->payment_type);
        }

        $perPage = $request->get('per_page', 15);
        // Limit max per_page to prevent performance issues
        $perPage = min($perPage, 1000);
        
        $teams = $query->paginate($perPage);
        return TeamResource::collection($teams);
    }

    public function store(Request $request)
    {
        $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:255',
            'role' => 'required|in:Admin,Project Manager,Developer,Business Developer,Client',
            'payment_type' => 'nullable|in:salary,project_based',
            'salary_amount' => 'nullable|numeric|min:0',
            'skills' => 'nullable|array',
            'joining_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'user_id' => 'nullable|exists:users,id',
        ]);

        $team = $this->teamRepository->create($request->all());
        return new TeamResource($team);
    }

    public function show(int $id)
    {
        $team = $this->teamRepository->find($id);
        if (!$team) {
            return response()->json(['message' => 'Team member not found'], 404);
        }
        return new TeamResource($team->load('projects', 'developerPayments'));
    }

    public function update(Request $request, int $id)
    {
        $request->validate([
            'full_name' => 'sometimes|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:255',
            'role' => 'sometimes|in:Admin,Project Manager,Developer,Business Developer,Client',
            'payment_type' => 'nullable|in:salary,project_based',
            'salary_amount' => 'nullable|numeric|min:0',
            'skills' => 'nullable|array',
            'joining_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'user_id' => 'nullable|exists:users,id',
        ]);

        $team = $this->teamRepository->update($id, $request->all());
        if (!$team) {
            return response()->json(['message' => 'Team member not found'], 404);
        }
        return new TeamResource($this->teamRepository->find($id));
    }

    public function destroy(int $id)
    {
        $deleted = $this->teamRepository->delete($id);
        if (!$deleted) {
            return response()->json(['message' => 'Team member not found'], 404);
        }
        return response()->json(['message' => 'Team member deleted successfully']);
    }
}

