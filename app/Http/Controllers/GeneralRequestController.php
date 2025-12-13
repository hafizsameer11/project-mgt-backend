<?php

namespace App\Http\Controllers;

use App\Models\GeneralRequest;
use Illuminate\Http\Request;

class GeneralRequestController extends Controller
{
    public function index(Request $request)
    {
        $query = GeneralRequest::query()->with('team', 'approver');

        if ($request->has('team_id')) {
            $query->where('team_id', $request->team_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $requests = $query->orderBy('created_at', 'desc')->paginate(15);
        return response()->json($requests);
    }

    public function store(Request $request)
    {
        $request->validate([
            'team_id' => 'required|exists:teams,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|in:Equipment,Software,Training,Other',
        ]);

        $generalRequest = GeneralRequest::create([
            'team_id' => $request->team_id,
            'title' => $request->title,
            'description' => $request->description,
            'category' => $request->category,
            'status' => 'Pending',
        ]);

        // Notify admin
        $admins = \App\Models\User::where('role', 'Admin')->get();
        foreach ($admins as $admin) {
            $admin->notify(new \App\Notifications\GeneralRequestNotification($generalRequest->load('team'), 'created'));
        }

        return response()->json($generalRequest->load('team'), 201);
    }

    public function update(Request $request, int $id)
    {
        $generalRequest = GeneralRequest::find($id);
        if (!$generalRequest) {
            return response()->json(['message' => 'Request not found'], 404);
        }

        $request->validate([
            'status' => 'required|in:Pending,Approved,Rejected,In Progress',
            'response' => 'nullable|string',
        ]);

        $oldStatus = $generalRequest->status;
        $generalRequest->update([
            'status' => $request->status,
            'approved_by' => $request->status !== 'Pending' ? $request->user()->id : null,
            'response' => $request->response,
        ]);

        // Notify requester if status changed from Pending
        if ($oldStatus === 'Pending' && $request->status !== 'Pending') {
            $team = $generalRequest->team;
            if ($team && $team->user) {
                $action = $request->status === 'Approved' ? 'approved' : ($request->status === 'In Progress' ? 'in_progress' : 'rejected');
                $team->user->notify(new \App\Notifications\GeneralRequestNotification($generalRequest->load('team'), $action));
            }
        }

        return response()->json($generalRequest->load('team', 'approver'));
    }
}

