<?php

namespace App\Http\Controllers;

use App\Models\Income;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class IncomeController extends Controller
{
    protected $fileUploadService;

    public function __construct(FileUploadService $fileUploadService)
    {
        $this->fileUploadService = $fileUploadService;
    }

    public function index(Request $request)
    {
        $query = Income::with('project', 'creator');

        if ($request->has('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        if ($request->has('income_type')) {
            $query->where('income_type', $request->income_type);
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('income_date', [$request->start_date, $request->end_date]);
        }

        $incomes = $query->orderBy('income_date', 'desc')->paginate(15);
        return response()->json($incomes);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'amount' => 'required|numeric|min:0',
            'currency' => 'nullable|string|max:3',
            'income_date' => 'required|date',
            'income_type' => 'nullable|in:project,other,investment,service,consultation',
            'project_id' => 'nullable|exists:projects,id',
            'notes' => 'nullable|string',
            'receipt' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $incomeNo = 'INC-' . strtoupper(Str::random(8));

        $data = $request->only([
            'title',
            'description',
            'amount',
            'currency',
            'income_date',
            'income_type',
            'project_id',
            'notes',
        ]);
        $data['income_no'] = $incomeNo;
        $data['created_by'] = $request->user()->id;
        $data['currency'] = $data['currency'] ?? 'PKR';
        $data['income_type'] = $data['income_type'] ?? 'other';

        if ($request->hasFile('receipt')) {
            $data['receipt_path'] = $this->fileUploadService->upload($request->file('receipt'), 'incomes');
        }

        $income = Income::create($data);
        return response()->json($income->load('project', 'creator'), 201);
    }

    public function show(int $id)
    {
        $income = Income::with('project', 'creator')->find($id);
        if (!$income) {
            return response()->json(['message' => 'Income not found'], 404);
        }
        return response()->json($income);
    }

    public function update(Request $request, int $id)
    {
        $income = Income::find($id);
        if (!$income) {
            return response()->json(['message' => 'Income not found'], 404);
        }

        $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'amount' => 'sometimes|numeric|min:0',
            'currency' => 'nullable|string|max:3',
            'income_date' => 'sometimes|date',
            'income_type' => 'nullable|in:project,other,investment,service,consultation',
            'project_id' => 'nullable|exists:projects,id',
            'notes' => 'nullable|string',
            'receipt' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $data = $request->only([
            'title',
            'description',
            'amount',
            'currency',
            'income_date',
            'income_type',
            'project_id',
            'notes',
        ]);

        if ($request->hasFile('receipt')) {
            $data['receipt_path'] = $this->fileUploadService->upload($request->file('receipt'), 'incomes');
        }

        $income->update($data);
        return response()->json($income->load('project', 'creator'));
    }

    public function destroy(int $id)
    {
        $income = Income::find($id);
        if (!$income) {
            return response()->json(['message' => 'Income not found'], 404);
        }
        $income->delete();
        return response()->json(['message' => 'Income deleted successfully']);
    }
}
