<?php

namespace App\Http\Controllers;

use App\Models\ExpenseCategory;
use Illuminate\Http\Request;

class ExpenseCategoryController extends Controller
{
    public function index()
    {
        $categories = ExpenseCategory::where('is_active', true)->get();
        return response()->json($categories);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'code' => 'nullable|string|max:255|unique:expense_categories,code',
            'is_active' => 'nullable|boolean',
        ]);

        $category = ExpenseCategory::create($request->all());
        return response()->json($category, 201);
    }

    public function update(Request $request, int $id)
    {
        $category = ExpenseCategory::find($id);
        if (!$category) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'code' => 'nullable|string|max:255|unique:expense_categories,code,' . $id,
            'is_active' => 'nullable|boolean',
        ]);

        $category->update($request->all());
        return response()->json($category);
    }

    public function destroy(int $id)
    {
        $category = ExpenseCategory::find($id);
        if (!$category) {
            return response()->json(['message' => 'Category not found'], 404);
        }
        $category->delete();
        return response()->json(['message' => 'Category deleted successfully']);
    }
}
