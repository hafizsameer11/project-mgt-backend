<?php

namespace App\Http\Controllers;

use App\Models\AdvancePayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdvancePaymentController extends Controller
{
    public function index(Request $request)
    {
        $query = AdvancePayment::with('user', 'approver');

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('month')) {
            $query->whereMonth('payment_date', $request->month);
        }

        if ($request->has('year')) {
            $query->whereYear('payment_date', $request->year);
        }

        $payments = $query->orderBy('payment_date', 'desc')->paginate(15);
        return response()->json($payments);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'monthly_salary' => 'nullable|numeric|min:0',
            'amount' => 'required|numeric|min:0',
            'currency' => 'nullable|string|max:10',
            'payment_date' => 'required|date',
            'description' => 'nullable|string',
            'status' => 'nullable|in:pending,approved,paid',
            'notes' => 'nullable|string',
        ]);

        $validated['currency'] = $validated['currency'] ?? 'PKR';
        $validated['status'] = $validated['status'] ?? 'pending';

        $payment = AdvancePayment::create($validated);
        return response()->json($payment->load('user', 'approver'), 201);
    }

    public function show(int $id)
    {
        $payment = AdvancePayment::with('user', 'approver')->find($id);
        if (!$payment) {
            return response()->json(['message' => 'Advance payment not found'], 404);
        }
        return response()->json($payment);
    }

    public function update(Request $request, int $id)
    {
        $payment = AdvancePayment::find($id);
        if (!$payment) {
            return response()->json(['message' => 'Advance payment not found'], 404);
        }

        $validated = $request->validate([
            'monthly_salary' => 'nullable|numeric|min:0',
            'amount' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:10',
            'payment_date' => 'nullable|date',
            'description' => 'nullable|string',
            'status' => 'nullable|in:pending,approved,paid',
            'notes' => 'nullable|string',
        ]);

        // If status is being changed to approved, set approved_by and approved_at
        if (isset($validated['status']) && $validated['status'] === 'approved' && $payment->status !== 'approved') {
            $validated['approved_by'] = $request->user()->id;
            $validated['approved_at'] = now();
        }

        $payment->update($validated);
        return response()->json($payment->load('user', 'approver'));
    }

    public function destroy(int $id)
    {
        $payment = AdvancePayment::find($id);
        if (!$payment) {
            return response()->json(['message' => 'Advance payment not found'], 404);
        }

        $payment->delete();
        return response()->json(['message' => 'Advance payment deleted successfully']);
    }

    public function approve(int $id, Request $request)
    {
        $payment = AdvancePayment::find($id);
        if (!$payment) {
            return response()->json(['message' => 'Advance payment not found'], 404);
        }

        $payment->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return response()->json($payment->load('user', 'approver'));
    }

    public function markAsPaid(int $id)
    {
        $payment = AdvancePayment::find($id);
        if (!$payment) {
            return response()->json(['message' => 'Advance payment not found'], 404);
        }

        $payment->update([
            'status' => 'paid',
        ]);

        return response()->json($payment->load('user', 'approver'));
    }

    public function getUserSummary(Request $request, int $userId)
    {
        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);

        // Get user's monthly salary (from latest advance payment or first one)
        $latestPayment = AdvancePayment::where('user_id', $userId)
            ->whereNotNull('monthly_salary')
            ->where('monthly_salary', '>', 0)
            ->orderBy('created_at', 'desc')
            ->first();

        $monthlySalary = $latestPayment ? $latestPayment->monthly_salary : 0;

        // Get all advance payments for the month
        $advancePayments = AdvancePayment::where('user_id', $userId)
            ->whereMonth('payment_date', $month)
            ->whereYear('payment_date', $year)
            ->where('status', '!=', 'pending')
            ->get();

        $totalAdvance = $advancePayments->sum('amount');
        $remainingSalary = max(0, $monthlySalary - $totalAdvance);

        return response()->json([
            'user_id' => $userId,
            'month' => $month,
            'year' => $year,
            'monthly_salary' => $monthlySalary,
            'total_advance' => $totalAdvance,
            'remaining_salary' => $remainingSalary,
            'payments' => $advancePayments,
        ]);
    }
}
