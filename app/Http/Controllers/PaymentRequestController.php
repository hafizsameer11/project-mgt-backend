<?php

namespace App\Http\Controllers;

use App\Models\PaymentRequest;
use Illuminate\Http\Request;

class PaymentRequestController extends Controller
{
    public function index(Request $request)
    {
        $query = PaymentRequest::query()->with('team', 'project', 'approver');

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
            'project_id' => 'nullable|exists:projects,id',
            'amount' => 'required|numeric|min:0',
            'reason' => 'nullable|string',
        ]);

        $paymentRequest = PaymentRequest::create([
            'team_id' => $request->team_id,
            'project_id' => $request->project_id,
            'amount' => $request->amount,
            'reason' => $request->reason,
            'status' => 'Pending',
        ]);

        // Notify admin
        $admins = \App\Models\User::where('role', 'Admin')->get();
        foreach ($admins as $admin) {
            $admin->notify(new \App\Notifications\PaymentRequestNotification($paymentRequest->load('team', 'project'), 'created'));
        }

        return response()->json($paymentRequest->load('team', 'project'), 201);
    }

    public function update(Request $request, int $id)
    {
        $paymentRequest = PaymentRequest::find($id);
        if (!$paymentRequest) {
            return response()->json(['message' => 'Payment request not found'], 404);
        }

        $request->validate([
            'status' => 'required|in:Pending,Approved,Rejected',
            'rejection_reason' => 'nullable|string',
        ]);

        $oldStatus = $paymentRequest->status;
        $paymentRequest->update([
            'status' => $request->status,
            'approved_by' => $request->status !== 'Pending' ? $request->user()->id : null,
            'rejection_reason' => $request->rejection_reason,
        ]);

        // Notify requester if status changed from Pending
        if ($oldStatus === 'Pending' && $request->status !== 'Pending') {
            $team = $paymentRequest->team;
            if ($team && $team->user) {
                $action = $request->status === 'Approved' ? 'approved' : 'rejected';
                $team->user->notify(new \App\Notifications\PaymentRequestNotification($paymentRequest->load('team', 'project'), $action));
            }
        }

        return response()->json($paymentRequest->load('team', 'project', 'approver'));
    }
}

