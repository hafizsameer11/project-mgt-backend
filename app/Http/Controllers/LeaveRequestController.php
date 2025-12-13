<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use Illuminate\Http\Request;

class LeaveRequestController extends Controller
{
    public function index(Request $request)
    {
        $query = LeaveRequest::query()->with('team', 'approver');

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
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'type' => 'nullable|in:Sick Leave,Vacation,Personal,Other',
            'reason' => 'nullable|string',
        ]);

        $startDate = \Carbon\Carbon::parse($request->start_date);
        $endDate = \Carbon\Carbon::parse($request->end_date);
        $days = $startDate->diffInDays($endDate) + 1;

        $leaveRequest = LeaveRequest::create([
            'team_id' => $request->team_id,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'days' => $days,
            'type' => $request->type,
            'reason' => $request->reason,
            'status' => 'Pending',
        ]);

        // Notify admin
        $admins = \App\Models\User::where('role', 'Admin')->get();
        foreach ($admins as $admin) {
            $admin->notify(new \App\Notifications\LeaveRequestNotification($leaveRequest->load('team'), 'created'));
        }

        return response()->json($leaveRequest->load('team'), 201);
    }

    public function update(Request $request, int $id)
    {
        $leaveRequest = LeaveRequest::find($id);
        if (!$leaveRequest) {
            return response()->json(['message' => 'Leave request not found'], 404);
        }

        $request->validate([
            'status' => 'required|in:Pending,Approved,Rejected',
            'rejection_reason' => 'nullable|string',
        ]);

        $oldStatus = $leaveRequest->status;
        $leaveRequest->update([
            'status' => $request->status,
            'approved_by' => $request->status !== 'Pending' ? $request->user()->id : null,
            'rejection_reason' => $request->rejection_reason,
        ]);

        // Notify requester if status changed from Pending
        if ($oldStatus === 'Pending' && $request->status !== 'Pending') {
            $team = $leaveRequest->team;
            if ($team && $team->user) {
                $action = $request->status === 'Approved' ? 'approved' : 'rejected';
                $team->user->notify(new \App\Notifications\LeaveRequestNotification($leaveRequest->load('team'), $action));
            }
        }

        return response()->json($leaveRequest->load('team', 'approver'));
    }
}

