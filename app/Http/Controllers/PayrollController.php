<?php

namespace App\Http\Controllers;

use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PayrollController extends Controller
{
    public function index(Request $request)
    {
        $query = Payroll::with('user', 'team', 'processor', 'items');

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $payrolls = $query->orderBy('pay_date', 'desc')->paginate(15);
        return response()->json($payrolls);
    }

    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'team_id' => 'nullable|exists:teams,id',
            'pay_period_start' => 'required|date',
            'pay_period_end' => 'required|date',
            'pay_date' => 'required|date',
            'gross_salary' => 'required|numeric|min:0',
            'items' => 'required|array',
            'items.*.type' => 'required|in:earning,deduction',
            'items.*.item_name' => 'required|string',
            'items.*.amount' => 'required|numeric',
            'items.*.description' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $payrollNo = 'PAY-' . strtoupper(Str::random(8));

        $totalDeductions = collect($request->items)
            ->where('type', 'deduction')
            ->sum('amount');
        $totalAllowances = collect($request->items)
            ->where('type', 'earning')
            ->sum('amount') - $request->gross_salary;

        $netSalary = $request->gross_salary + $totalAllowances - $totalDeductions;

        $payroll = Payroll::create([
            'user_id' => $request->user_id,
            'team_id' => $request->team_id,
            'payroll_no' => $payrollNo,
            'pay_period_start' => $request->pay_period_start,
            'pay_period_end' => $request->pay_period_end,
            'pay_date' => $request->pay_date,
            'gross_salary' => $request->gross_salary,
            'total_deductions' => $totalDeductions,
            'total_allowances' => $totalAllowances,
            'net_salary' => $netSalary,
            'status' => 'draft',
            'notes' => $request->notes,
        ]);

        foreach ($request->items as $item) {
            PayrollItem::create([
                'payroll_id' => $payroll->id,
                'type' => $item['type'],
                'item_name' => $item['item_name'],
                'amount' => $item['amount'],
                'description' => $item['description'] ?? null,
            ]);
        }

        return response()->json($payroll->load('user', 'team', 'items'), 201);
    }

    public function show(int $id)
    {
        $payroll = Payroll::with('user', 'team', 'processor', 'items')->find($id);
        if (!$payroll) {
            return response()->json(['message' => 'Payroll not found'], 404);
        }
        return response()->json($payroll);
    }

    public function update(Request $request, int $id)
    {
        $payroll = Payroll::find($id);
        if (!$payroll) {
            return response()->json(['message' => 'Payroll not found'], 404);
        }

        $request->validate([
            'pay_period_start' => 'sometimes|date',
            'pay_period_end' => 'sometimes|date',
            'pay_date' => 'sometimes|date',
            'gross_salary' => 'sometimes|numeric|min:0',
            'status' => 'sometimes|in:draft,processed,paid,cancelled',
            'notes' => 'nullable|string',
        ]);

        $payroll->update($request->all());
        return response()->json($payroll->load('user', 'team', 'items'));
    }

    public function process(Request $request, int $id)
    {
        $payroll = Payroll::find($id);
        if (!$payroll) {
            return response()->json(['message' => 'Payroll not found'], 404);
        }

        $payroll->update([
            'status' => 'processed',
            'processed_by' => $request->user()->id,
            'processed_at' => now(),
        ]);

        return response()->json($payroll->load('processor'));
    }

    public function destroy(int $id)
    {
        $payroll = Payroll::find($id);
        if (!$payroll) {
            return response()->json(['message' => 'Payroll not found'], 404);
        }
        $payroll->items()->delete();
        $payroll->delete();
        return response()->json(['message' => 'Payroll deleted successfully']);
    }
}
