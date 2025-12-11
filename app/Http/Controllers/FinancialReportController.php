<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\Expense;
use App\Models\ClientPayment;
use App\Models\VendorBill;
use App\Models\VendorPayment;
use Illuminate\Http\Request;
use Carbon\Carbon;

class FinancialReportController extends Controller
{
    public function profitLoss(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->endOfMonth()->toDateString());

        // Revenue
        $revenueAccounts = ChartOfAccount::where('account_type', 'revenue')
            ->where('is_active', true)
            ->get();

        $revenue = 0;
        foreach ($revenueAccounts as $account) {
            $revenue += $this->getAccountBalance($account->id, $startDate, $endDate);
        }

        // Client Payments (Revenue)
        $clientPayments = ClientPayment::whereBetween('created_at', [$startDate, $endDate])
            ->sum('amount_paid');
        $revenue += $clientPayments;

        // Expenses
        $expenseAccounts = ChartOfAccount::where('account_type', 'expense')
            ->where('is_active', true)
            ->get();

        $expenses = 0;
        foreach ($expenseAccounts as $account) {
            $expenses += $this->getAccountBalance($account->id, $startDate, $endDate);
        }

        // Expense Records
        $expenseRecords = Expense::where('status', 'approved')
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->sum('amount');
        $expenses += $expenseRecords;

        // Vendor Bills (Expenses)
        $vendorBills = VendorBill::where('status', '!=', 'cancelled')
            ->whereBetween('bill_date', [$startDate, $endDate])
            ->sum('total_amount');
        $expenses += $vendorBills;

        $grossProfit = $revenue - $expenses;
        $netProfit = $grossProfit; // Simplified

        return response()->json([
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'revenue' => [
                'total' => $revenue,
                'client_payments' => $clientPayments,
            ],
            'expenses' => [
                'total' => $expenses,
                'expense_records' => $expenseRecords,
                'vendor_bills' => $vendorBills,
            ],
            'gross_profit' => $grossProfit,
            'net_profit' => $netProfit,
        ]);
    }

    public function balanceSheet(Request $request)
    {
        $asOfDate = $request->input('as_of_date', Carbon::now()->toDateString());

        // Assets
        $assets = ChartOfAccount::where('account_type', 'asset')
            ->where('is_active', true)
            ->get()
            ->map(function ($account) use ($asOfDate) {
                return [
                    'account_code' => $account->account_code,
                    'account_name' => $account->account_name,
                    'balance' => $this->getAccountBalance($account->id, null, $asOfDate),
                ];
            });

        $totalAssets = $assets->sum('balance');

        // Add physical assets
        $physicalAssets = \App\Models\Asset::where('status', 'active')
            ->sum('current_value');
        $totalAssets += $physicalAssets;

        // Liabilities
        $liabilities = ChartOfAccount::where('account_type', 'liability')
            ->where('is_active', true)
            ->get()
            ->map(function ($account) use ($asOfDate) {
                return [
                    'account_code' => $account->account_code,
                    'account_name' => $account->account_name,
                    'balance' => abs($this->getAccountBalance($account->id, null, $asOfDate)),
                ];
            });

        $totalLiabilities = $liabilities->sum('balance');

        // Add vendor bills (Accounts Payable)
        $vendorBillsPayable = VendorBill::where('status', '!=', 'paid')
            ->where('status', '!=', 'cancelled')
            ->sum('remaining_amount');
        $totalLiabilities += $vendorBillsPayable;

        // Equity
        $equity = ChartOfAccount::where('account_type', 'equity')
            ->where('is_active', true)
            ->get()
            ->map(function ($account) use ($asOfDate) {
                return [
                    'account_code' => $account->account_code,
                    'account_name' => $account->account_name,
                    'balance' => $this->getAccountBalance($account->id, null, $asOfDate),
                ];
            });

        $totalEquity = $equity->sum('balance');
        $retainedEarnings = $totalAssets - $totalLiabilities - $totalEquity;
        $totalEquity += $retainedEarnings;

        return response()->json([
            'as_of_date' => $asOfDate,
            'assets' => [
                'items' => $assets,
                'physical_assets' => $physicalAssets,
                'total' => $totalAssets,
            ],
            'liabilities' => [
                'items' => $liabilities,
                'accounts_payable' => $vendorBillsPayable,
                'total' => $totalLiabilities,
            ],
            'equity' => [
                'items' => $equity,
                'retained_earnings' => $retainedEarnings,
                'total' => $totalEquity,
            ],
            'total_liabilities_and_equity' => $totalLiabilities + $totalEquity,
        ]);
    }

    public function cashFlow(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->endOfMonth()->toDateString());

        // Operating Activities
        $clientPayments = ClientPayment::whereBetween('created_at', [$startDate, $endDate])
            ->sum('amount_paid');
        $vendorPayments = VendorPayment::whereBetween('payment_date', [$startDate, $endDate])
            ->sum('amount');
        $expenses = Expense::where('status', 'approved')
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->sum('amount');

        $operatingCashFlow = $clientPayments - $vendorPayments - $expenses;

        // Investing Activities (simplified)
        $assetPurchases = \App\Models\Asset::whereBetween('purchase_date', [$startDate, $endDate])
            ->sum('purchase_cost');
        $investingCashFlow = -$assetPurchases;

        // Financing Activities (simplified)
        $financingCashFlow = 0;

        $netCashFlow = $operatingCashFlow + $investingCashFlow + $financingCashFlow;

        return response()->json([
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'operating_activities' => [
                'cash_in' => $clientPayments,
                'cash_out' => $vendorPayments + $expenses,
                'net' => $operatingCashFlow,
            ],
            'investing_activities' => [
                'asset_purchases' => $assetPurchases,
                'net' => $investingCashFlow,
            ],
            'financing_activities' => [
                'net' => $financingCashFlow,
            ],
            'net_cash_flow' => $netCashFlow,
        ]);
    }

    private function getAccountBalance($accountId, $startDate = null, $endDate = null)
    {
        $query = \App\Models\JournalEntryItem::where('account_id', $accountId)
            ->whereHas('journalEntry', function ($q) use ($startDate, $endDate) {
                $q->where('status', 'posted');
                if ($startDate) {
                    $q->where('entry_date', '>=', $startDate);
                }
                if ($endDate) {
                    $q->where('entry_date', '<=', $endDate);
                }
            });

        $debits = (clone $query)->where('type', 'debit')->sum('amount');
        $credits = (clone $query)->where('type', 'credit')->sum('amount');

        $account = ChartOfAccount::find($accountId);
        $openingBalance = $account ? $account->opening_balance : 0;

        // For assets and expenses, debits increase, credits decrease
        // For liabilities, equity, and revenue, credits increase, debits decrease
        if (in_array($account->account_type ?? '', ['asset', 'expense'])) {
            return $openingBalance + $debits - $credits;
        } else {
            return $openingBalance + $credits - $debits;
        }
    }
}
