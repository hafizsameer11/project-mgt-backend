<?php

namespace App\Http\Controllers;

use App\Models\JournalEntry;
use App\Models\JournalEntryItem;
use App\Models\ChartOfAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class JournalEntryController extends Controller
{
    public function index(Request $request)
    {
        $query = JournalEntry::with('creator', 'poster', 'items.account');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $entries = $query->orderBy('entry_date', 'desc')->paginate(15);
        return response()->json($entries);
    }

    public function store(Request $request)
    {
        $request->validate([
            'entry_date' => 'required|date',
            'description' => 'nullable|string',
            'reference' => 'nullable|string',
            'items' => 'required|array|min:2',
            'items.*.account_id' => 'required|exists:chart_of_accounts,id',
            'items.*.type' => 'required|in:debit,credit',
            'items.*.amount' => 'required|numeric|min:0',
            'items.*.description' => 'nullable|string',
        ]);

        // Validate double-entry accounting
        $totalDebits = collect($request->items)->where('type', 'debit')->sum('amount');
        $totalCredits = collect($request->items)->where('type', 'credit')->sum('amount');

        if (abs($totalDebits - $totalCredits) > 0.01) {
            return response()->json([
                'message' => 'Total debits must equal total credits',
                'debits' => $totalDebits,
                'credits' => $totalCredits,
            ], 422);
        }

        return DB::transaction(function () use ($request) {
            $entryNo = 'JE-' . strtoupper(Str::random(8));

            $entry = JournalEntry::create([
                'entry_no' => $entryNo,
                'entry_date' => $request->entry_date,
                'description' => $request->description,
                'reference' => $request->reference,
                'status' => 'draft',
                'created_by' => $request->user()->id,
            ]);

            foreach ($request->items as $item) {
                JournalEntryItem::create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $item['account_id'],
                    'type' => $item['type'],
                    'amount' => $item['amount'],
                    'description' => $item['description'] ?? null,
                ]);
            }

            return response()->json($entry->load('creator', 'items.account'), 201);
        });
    }

    public function show(int $id)
    {
        $entry = JournalEntry::with('creator', 'poster', 'items.account')->find($id);
        if (!$entry) {
            return response()->json(['message' => 'Journal entry not found'], 404);
        }
        return response()->json($entry);
    }

    public function update(Request $request, int $id)
    {
        $entry = JournalEntry::find($id);
        if (!$entry) {
            return response()->json(['message' => 'Journal entry not found'], 404);
        }

        if ($entry->status === 'posted') {
            return response()->json(['message' => 'Cannot edit posted entries'], 422);
        }

        $request->validate([
            'entry_date' => 'sometimes|date',
            'description' => 'nullable|string',
            'reference' => 'nullable|string',
            'items' => 'sometimes|array|min:2',
            'items.*.account_id' => 'required|exists:chart_of_accounts,id',
            'items.*.type' => 'required|in:debit,credit',
            'items.*.amount' => 'required|numeric|min:0',
            'items.*.description' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($request, $entry) {
            if ($request->has('items')) {
                $totalDebits = collect($request->items)->where('type', 'debit')->sum('amount');
                $totalCredits = collect($request->items)->where('type', 'credit')->sum('amount');

                if (abs($totalDebits - $totalCredits) > 0.01) {
                    return response()->json([
                        'message' => 'Total debits must equal total credits',
                    ], 422);
                }

                $entry->items()->delete();

                foreach ($request->items as $item) {
                    JournalEntryItem::create([
                        'journal_entry_id' => $entry->id,
                        'account_id' => $item['account_id'],
                        'type' => $item['type'],
                        'amount' => $item['amount'],
                        'description' => $item['description'] ?? null,
                    ]);
                }
            }

            $entry->update($request->only(['entry_date', 'description', 'reference']));
            return response()->json($entry->load('creator', 'items.account'));
        });
    }

    public function post(Request $request, int $id)
    {
        $entry = JournalEntry::with('items')->find($id);
        if (!$entry) {
            return response()->json(['message' => 'Journal entry not found'], 404);
        }

        if ($entry->status === 'posted') {
            return response()->json(['message' => 'Entry already posted'], 422);
        }

        return DB::transaction(function () use ($entry, $request) {
            foreach ($entry->items as $item) {
                $account = ChartOfAccount::find($item->account_id);
                if ($account) {
                    if ($item->type === 'debit') {
                        $account->current_balance += $item->amount;
                    } else {
                        $account->current_balance -= $item->amount;
                    }
                    $account->save();
                }
            }

            $entry->update([
                'status' => 'posted',
                'posted_by' => $request->user()->id,
                'posted_at' => now(),
            ]);

            return response()->json($entry->load('poster', 'items.account'));
        });
    }

    public function destroy(int $id)
    {
        $entry = JournalEntry::find($id);
        if (!$entry) {
            return response()->json(['message' => 'Journal entry not found'], 404);
        }

        if ($entry->status === 'posted') {
            return response()->json(['message' => 'Cannot delete posted entries'], 422);
        }

        $entry->items()->delete();
        $entry->delete();
        return response()->json(['message' => 'Journal entry deleted successfully']);
    }
}
