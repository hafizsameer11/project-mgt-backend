<?php

namespace App\Http\Controllers;

use App\Models\AdvancePayment;
use Illuminate\Http\Request;

class AdvancePaymentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = AdvancePayment::with('user', 'creator');

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('month')) {
            $query->whereMonth('payment_date', $request->month);
        }

        if ($request->has('year')) {
            $query->whereYear('payment_date', $request->year);
        }

        $advancePayments = $query->orderBy('payment_date', 'desc')->paginate(15);
        return response()->json($advancePayments);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:0',
            'currency' => 'nullable|string|max:10',
            'payment_date' => 'required|date',
            'payment_method' => 'nullable|string|max:255',
            'monthly_salary' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $validated['created_by'] = $request->user()->id;
        $validated['currency'] = $validated['currency'] ?? 'PKR';

        $advancePayment = AdvancePayment::create($validated);
        return response()->json($advancePayment->load('user', 'creator'), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id)
    {
        $advancePayment = AdvancePayment::with('user', 'creator')->find($id);
        if (!$advancePayment) {
            return response()->json(['message' => 'Advance payment not found'], 404);
        }
        return response()->json($advancePayment);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int $id)
    {
        $advancePayment = AdvancePayment::find($id);
        if (!$advancePayment) {
            return response()->json(['message' => 'Advance payment not found'], 404);
        }

        $validated = $request->validate([
            'amount' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:10',
            'payment_date' => 'nullable|date',
            'payment_method' => 'nullable|string|max:255',
            'monthly_salary' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $advancePayment->update($validated);
        return response()->json($advancePayment->load('user', 'creator'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id)
    {
        $advancePayment = AdvancePayment::find($id);
        if (!$advancePayment) {
            return response()->json(['message' => 'Advance payment not found'], 404);
        }

        $advancePayment->delete();
        return response()->json(['message' => 'Advance payment deleted successfully']);
    }

    /**
     * Get monthly summary for a user
     */
    public function monthlySummary(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2020',
        ]);

        $advancePayments = AdvancePayment::where('user_id', $request->user_id)
            ->whereMonth('payment_date', $request->month)
            ->whereYear('payment_date', $request->year)
            ->get();

        $totalAdvance = $advancePayments->sum('amount');
        $monthlySalary = $advancePayments->first()->monthly_salary ?? 0;
        $remainingSalary = $monthlySalary > 0 ? max(0, $monthlySalary - $totalAdvance) : 0;

        return response()->json([
            'user_id' => $request->user_id,
            'month' => $request->month,
            'year' => $request->year,
            'total_advance' => $totalAdvance,
            'monthly_salary' => $monthlySalary,
            'remaining_salary' => $remainingSalary,
            'payments' => $advancePayments,
        ]);
    }
}
