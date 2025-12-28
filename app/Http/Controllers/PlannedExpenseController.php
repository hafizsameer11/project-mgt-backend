<?php

namespace App\Http\Controllers;

use App\Models\PlannedExpense;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PlannedExpenseController extends Controller
{
    public function index(Request $request)
    {
        $query = PlannedExpense::with('category');
        
        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }
        
        $plannedExpenses = $query->orderBy('day_of_month')->get();
        return response()->json($plannedExpenses);
    }

    public function store(Request $request)
    {
        $request->validate([
            'expense_category_id' => 'nullable|exists:expense_categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'amount' => 'required|numeric|min:0',
            'currency' => 'nullable|string|max:3',
            'day_of_month' => 'required|integer|min:1|max:31',
            'is_active' => 'nullable|boolean',
            'is_recurring' => 'nullable|boolean',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'specific_month' => 'nullable|date',
        ]);

        $data = $request->all();
        
        // If recurring, ensure start_date is set
        if ($data['is_recurring'] ?? true) {
            if (empty($data['start_date'])) {
                $data['start_date'] = now()->startOfMonth();
            }
        } else {
            // One-time expense, ensure specific_month is set
            if (empty($data['specific_month'])) {
                $data['specific_month'] = now()->startOfMonth();
            }
        }

        $plannedExpense = PlannedExpense::create($data);
        return response()->json($plannedExpense->load('category'), 201);
    }

    public function show(int $id)
    {
        $plannedExpense = PlannedExpense::with('category')->find($id);
        if (!$plannedExpense) {
            return response()->json(['message' => 'Planned expense not found'], 404);
        }
        return response()->json($plannedExpense);
    }

    public function update(Request $request, int $id)
    {
        $plannedExpense = PlannedExpense::find($id);
        if (!$plannedExpense) {
            return response()->json(['message' => 'Planned expense not found'], 404);
        }

        $request->validate([
            'expense_category_id' => 'nullable|exists:expense_categories,id',
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'amount' => 'sometimes|numeric|min:0',
            'currency' => 'nullable|string|max:3',
            'day_of_month' => 'sometimes|integer|min:1|max:31',
            'is_active' => 'nullable|boolean',
            'is_recurring' => 'nullable|boolean',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'specific_month' => 'nullable|date',
        ]);

        $data = $request->all();
        
        // Handle recurring vs one-time logic
        if (isset($data['is_recurring'])) {
            if ($data['is_recurring']) {
                if (empty($data['start_date']) && !$plannedExpense->start_date) {
                    $data['start_date'] = now()->startOfMonth();
                }
                $data['specific_month'] = null;
            } else {
                if (empty($data['specific_month']) && !$plannedExpense->specific_month) {
                    $data['specific_month'] = now()->startOfMonth();
                }
                $data['start_date'] = null;
                $data['end_date'] = null;
            }
        }

        $plannedExpense->update($data);
        return response()->json($plannedExpense->load('category'));
    }

    public function destroy(int $id)
    {
        $plannedExpense = PlannedExpense::find($id);
        if (!$plannedExpense) {
            return response()->json(['message' => 'Planned expense not found'], 404);
        }
        $plannedExpense->delete();
        return response()->json(['message' => 'Planned expense deleted successfully']);
    }

    /**
     * Get monthly expense summary
     * Returns planned expenses, actual expenses, and income for a given month
     */
    public function monthlySummary(Request $request)
    {
        $year = $request->get('year', now()->year);
        $month = $request->get('month', now()->month);
        
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();
        
        // Get planned expenses for this month
        $plannedExpenses = PlannedExpense::with('category')
            ->where('is_active', true)
            ->get()
            ->filter(function ($expense) use ($year, $month) {
                return $expense->appliesToMonth($year, $month);
            });
        
        $totalPlanned = $plannedExpenses->sum('amount');
        
        // Get actual expenses for this month
        $actualExpenses = Expense::whereBetween('expense_date', [$startDate, $endDate])
            ->whereIn('status', ['approved', 'paid'])
            ->sum('amount');
        
        // Get income for this month (client payments) - use payment_date if available, otherwise created_at
        $clientPaymentIncome = \App\Models\ClientPayment::where(function($query) use ($startDate, $endDate) {
            $query->where(function($q) use ($startDate, $endDate) {
                // Use payment_date if available
                $q->whereNotNull('payment_date')
                  ->whereBetween('payment_date', [$startDate, $endDate]);
            })->orWhere(function($q) use ($startDate, $endDate) {
                // Fallback to created_at for records without payment_date
                $q->whereNull('payment_date')
                  ->whereBetween('created_at', [$startDate, $endDate]);
            });
        })->where('amount_paid', '>', 0)->sum('amount_paid');
        
        // Get separate income entries for this month
        $separateIncome = \App\Models\Income::whereBetween('income_date', [$startDate, $endDate])
            ->sum('amount');
        
        $currentIncome = $clientPaymentIncome + $separateIncome;
        
        // Get pending income (expected but not yet received) - based on payment records created this month
        $pendingIncome = \App\Models\ClientPayment::whereBetween('created_at', [$startDate, $endDate])
            ->where('remaining_amount', '>', 0)
            ->sum('remaining_amount');
        
        // Calculate required income
        $requiredIncome = $totalPlanned + $actualExpenses;
        $incomeGap = $requiredIncome - $currentIncome;
        
        return response()->json([
            'year' => $year,
            'month' => $month,
            'month_name' => $startDate->format('F Y'),
            'planned_expenses' => [
                'total' => $totalPlanned,
                'items' => $plannedExpenses->map(function ($expense) {
                    return [
                        'id' => $expense->id,
                        'name' => $expense->name,
                        'category' => $expense->category->name ?? 'N/A',
                        'amount' => $expense->amount,
                        'currency' => $expense->currency,
                        'day_of_month' => $expense->day_of_month,
                    ];
                }),
            ],
            'actual_expenses' => [
                'total' => $actualExpenses,
            ],
            'income' => [
                'current' => $currentIncome,
                'from_client_payments' => $clientPaymentIncome,
                'from_separate_income' => $separateIncome,
                'pending' => $pendingIncome,
                'total_expected' => $currentIncome + $pendingIncome,
            ],
            'summary' => [
                'total_expenses' => $totalPlanned + $actualExpenses,
                'required_income' => $requiredIncome,
                'income_gap' => $incomeGap,
                'is_sufficient' => $currentIncome >= $requiredIncome,
            ],
        ]);
    }
}
