<?php

namespace App\Http\Controllers;

use App\Models\ProjectPhase;
use Illuminate\Http\Request;

class ProjectPhaseController extends Controller
{
    public function index(Request $request)
    {
        $query = ProjectPhase::with('project');

        if ($request->has('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        $phases = $query->orderBy('order')->orderBy('deadline')->get();
        return response()->json($phases);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        
        // Only Admin and Project Manager can create phases
        if (!in_array($user->role, ['Admin', 'Project Manager'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'project_id' => 'required|exists:projects,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'nullable|date',
            'deadline' => 'required|date',
            'status' => 'nullable|in:Not Started,In Progress,Completed,Delayed',
            'order' => 'nullable|integer|min:0',
        ]);

        $data = $request->all();
        if (!isset($data['order'])) {
            // Auto-increment order
            $maxOrder = ProjectPhase::where('project_id', $request->project_id)->max('order') ?? 0;
            $data['order'] = $maxOrder + 1;
        }

        $phase = ProjectPhase::create($data);
        return response()->json($phase->load('project'), 201);
    }

    public function show(int $id)
    {
        $phase = ProjectPhase::with('project')->find($id);
        if (!$phase) {
            return response()->json(['message' => 'Phase not found'], 404);
        }
        return response()->json($phase);
    }

    public function update(Request $request, int $id)
    {
        $user = $request->user();
        $phase = ProjectPhase::find($id);
        
        if (!$phase) {
            return response()->json(['message' => 'Phase not found'], 404);
        }

        // Only Admin and Project Manager can update phases
        if (!in_array($user->role, ['Admin', 'Project Manager'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'nullable|date',
            'deadline' => 'sometimes|required|date',
            'status' => 'nullable|in:Not Started,In Progress,Completed,Delayed',
            'order' => 'nullable|integer|min:0',
        ]);

        $phase->update($request->all());
        return response()->json($phase->load('project'));
    }

    public function destroy(int $id, Request $request)
    {
        $user = $request->user();
        $phase = ProjectPhase::find($id);
        
        if (!$phase) {
            return response()->json(['message' => 'Phase not found'], 404);
        }

        // Only Admin and Project Manager can delete phases
        if (!in_array($user->role, ['Admin', 'Project Manager'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $phase->delete();
        return response()->json(['message' => 'Phase deleted successfully']);
    }
}
