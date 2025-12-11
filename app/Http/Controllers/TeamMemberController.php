<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProjectResource;
use App\Http\Resources\TaskResource;
use App\Http\Resources\DeveloperPaymentResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TeamMemberController extends Controller
{
    public function dashboard(Request $request)
    {
        $user = $request->user();
        $team = \App\Models\Team::where('user_id', $user->id)->first();

        if (!$team) {
            return response()->json(['message' => 'Team member not found'], 404);
        }

        // Get projects
        $projects = $team->projects()->with('client', 'assignedBd')->get();

        // Get tasks
        $tasks = \App\Models\Task::where('assigned_to', $user->id)
            ->with('project')
            ->get();

        // Get payments with client payment status
        $payments = \App\Models\DeveloperPayment::where('developer_id', $team->id)
            ->with('project.client', 'paymentHistory')
            ->get();

        // Calculate stats
        $stats = [
            'total_projects' => $projects->count(),
            'active_tasks' => $tasks->where('status', '!=', 'Completed')->count(),
            'completed_tasks' => $tasks->where('status', 'Completed')->count(),
            'total_earned' => $payments->sum('amount_paid'),
            'pending_balance' => $payments->sum(function ($payment) {
                return $payment->remaining_amount ?? 0;
            }),
        ];

        return response()->json([
            'team' => $team,
            'stats' => $stats,
            'projects' => ProjectResource::collection($projects),
            'tasks' => TaskResource::collection($tasks),
            'payments' => DeveloperPaymentResource::collection($payments),
        ]);
    }

    public function createUserAccount(Request $request, $teamId)
    {
        $request->validate([
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $team = \App\Models\Team::find((int) $teamId);
        if (!$team) {
            return response()->json(['message' => 'Team member not found'], 404);
        }

        $user = \App\Models\User::create([
            'name' => $team->full_name,
            'email' => $request->email,
            'password' => \Illuminate\Support\Facades\Hash::make($request->password),
            'role' => $team->role,
        ]);

        $team->update(['user_id' => $user->id]);

        return response()->json([
            'message' => 'User account created successfully',
            'user' => $user,
        ], 201);
    }
}

