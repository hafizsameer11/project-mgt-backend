<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use Illuminate\Http\Request;

class ChartOfAccountController extends Controller
{
    public function index(Request $request)
    {
        $query = ChartOfAccount::query();

        if ($request->has('account_type')) {
            $query->where('account_type', $request->account_type);
        }
        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        $accounts = $query->with('parentAccount', 'childAccounts')->orderBy('account_code')->paginate(15);
        return response()->json($accounts);
    }

    public function store(Request $request)
    {
        $request->validate([
            'account_code' => 'required|string|max:255|unique:chart_of_accounts,account_code',
            'account_name' => 'required|string|max:255',
            'account_type' => 'required|in:asset,liability,equity,revenue,expense',
            'account_subtype' => 'nullable|string',
            'parent_account_id' => 'nullable|exists:chart_of_accounts,id',
            'opening_balance' => 'nullable|numeric',
            'is_active' => 'nullable|boolean',
            'description' => 'nullable|string',
        ]);

        $account = ChartOfAccount::create($request->all());
        return response()->json($account->load('parentAccount'), 201);
    }

    public function show(int $id)
    {
        $account = ChartOfAccount::with('parentAccount', 'childAccounts', 'journalEntryItems')->find($id);
        if (!$account) {
            return response()->json(['message' => 'Account not found'], 404);
        }
        return response()->json($account);
    }

    public function update(Request $request, int $id)
    {
        $account = ChartOfAccount::find($id);
        if (!$account) {
            return response()->json(['message' => 'Account not found'], 404);
        }

        $request->validate([
            'account_code' => 'sometimes|string|max:255|unique:chart_of_accounts,account_code,' . $id,
            'account_name' => 'sometimes|string|max:255',
            'account_type' => 'sometimes|in:asset,liability,equity,revenue,expense',
            'account_subtype' => 'nullable|string',
            'parent_account_id' => 'nullable|exists:chart_of_accounts,id',
            'opening_balance' => 'nullable|numeric',
            'is_active' => 'nullable|boolean',
            'description' => 'nullable|string',
        ]);

        $account->update($request->all());
        return response()->json($account->load('parentAccount'));
    }

    public function destroy(int $id)
    {
        $account = ChartOfAccount::find($id);
        if (!$account) {
            return response()->json(['message' => 'Account not found'], 404);
        }
        $account->delete();
        return response()->json(['message' => 'Account deleted successfully']);
    }
}
