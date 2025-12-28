<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ExpenseController extends Controller
{
    protected $fileUploadService;

    public function __construct(FileUploadService $fileUploadService)
    {
        $this->fileUploadService = $fileUploadService;
    }

    public function index(Request $request)
    {
        $query = Expense::with(['user', 'category', 'project', 'approver']);

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        $expenses = $query->orderBy('expense_date', 'desc')->paginate(15);
        
        // Ensure categories are loaded properly
        $expenses->getCollection()->transform(function ($expense) {
            if ($expense->category) {
                $expense->category_name = $expense->category->name;
            }
            return $expense;
        });
        
        return response()->json($expenses);
    }

    public function store(Request $request)
    {
        $request->validate([
            'expense_category_id' => 'nullable|exists:expense_categories,id',
            'project_id' => 'nullable|exists:projects,id',
            'expense_date' => 'required|date',
            'amount' => 'required|numeric|min:0',
            'currency' => 'nullable|string|max:3',
            'payment_method' => 'nullable|in:cash,card,bank_transfer,check,other',
            'description' => 'nullable|string',
            'receipt' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $expenseNo = 'EXP-' . strtoupper(Str::random(8));

        $data = $request->only([
            'expense_category_id',
            'project_id',
            'expense_date',
            'amount',
            'currency',
            'payment_method',
            'description',
        ]);
        $data['user_id'] = $request->user()->id;
        $data['expense_no'] = $expenseNo;
        $data['status'] = 'draft';

        if ($request->hasFile('receipt')) {
            $data['receipt_path'] = $this->fileUploadService->upload($request->file('receipt'), 'expenses');
        }

        $expense = Expense::create($data);
        return response()->json($expense->load('user', 'category', 'project'), 201);
    }

    public function show(int $id)
    {
        $expense = Expense::with('user', 'category', 'project', 'approver', 'approvals.approver')->find($id);
        if (!$expense) {
            return response()->json(['message' => 'Expense not found'], 404);
        }
        return response()->json($expense);
    }

    public function update(Request $request, int $id)
    {
        $expense = Expense::find($id);
        if (!$expense) {
            return response()->json(['message' => 'Expense not found'], 404);
        }

        $request->validate([
            'expense_category_id' => 'nullable|exists:expense_categories,id',
            'project_id' => 'nullable|exists:projects,id',
            'expense_date' => 'sometimes|date',
            'amount' => 'sometimes|numeric|min:0',
            'currency' => 'nullable|string|max:3',
            'payment_method' => 'nullable|in:cash,card,bank_transfer,check,other',
            'description' => 'nullable|string',
            'receipt' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $data = [];
        if ($request->has('expense_category_id')) $data['expense_category_id'] = $request->expense_category_id;
        if ($request->has('project_id')) $data['project_id'] = $request->project_id;
        if ($request->has('expense_date')) $data['expense_date'] = $request->expense_date;
        if ($request->has('amount')) $data['amount'] = $request->amount;
        if ($request->has('currency')) $data['currency'] = $request->currency;
        if ($request->has('payment_method')) $data['payment_method'] = $request->payment_method;
        if ($request->has('description')) $data['description'] = $request->description;

        if ($request->hasFile('receipt')) {
            $data['receipt_path'] = $this->fileUploadService->upload($request->file('receipt'), 'expenses');
        }

        $expense->update($data);
        return response()->json($expense->load('user', 'category', 'project'));
    }

    public function destroy(int $id)
    {
        $expense = Expense::find($id);
        if (!$expense) {
            return response()->json(['message' => 'Expense not found'], 404);
        }
        $expense->delete();
        return response()->json(['message' => 'Expense deleted successfully']);
    }

    public function submit(Request $request, int $id)
    {
        $expense = Expense::find($id);
        if (!$expense) {
            return response()->json(['message' => 'Expense not found'], 404);
        }

        if ($expense->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $expense->update(['status' => 'submitted']);
        return response()->json($expense);
    }

    public function approve(Request $request, int $id)
    {
        $request->validate([
            'comments' => 'nullable|string',
        ]);

        $expense = Expense::find($id);
        if (!$expense) {
            return response()->json(['message' => 'Expense not found'], 404);
        }

        $expense->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return response()->json($expense->load('approver'));
    }

    public function reject(Request $request, int $id)
    {
        $request->validate([
            'rejection_reason' => 'required|string',
        ]);

        $expense = Expense::find($id);
        if (!$expense) {
            return response()->json(['message' => 'Expense not found'], 404);
        }

        $expense->update([
            'status' => 'rejected',
            'rejection_reason' => $request->rejection_reason,
        ]);

        return response()->json($expense);
    }
}
