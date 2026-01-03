<?php

namespace App\Services;

use App\Repositories\LeadRepository;
use App\Repositories\ClientRepository;
use App\Repositories\ProjectRepository;
use App\Repositories\TaskRepository;
use App\Repositories\DeveloperPaymentRepository;
use App\Repositories\ClientPaymentRepository;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    protected $leadRepository;
    protected $clientRepository;
    protected $projectRepository;
    protected $taskRepository;
    protected $developerPaymentRepository;
    protected $clientPaymentRepository;

    public function __construct(
        LeadRepository $leadRepository,
        ClientRepository $clientRepository,
        ProjectRepository $projectRepository,
        TaskRepository $taskRepository,
        DeveloperPaymentRepository $developerPaymentRepository,
        ClientPaymentRepository $clientPaymentRepository
    ) {
        $this->leadRepository = $leadRepository;
        $this->clientRepository = $clientRepository;
        $this->projectRepository = $projectRepository;
        $this->taskRepository = $taskRepository;
        $this->developerPaymentRepository = $developerPaymentRepository;
        $this->clientPaymentRepository = $clientPaymentRepository;
    }

    public function getStats()
    {
        // If current month is December 2025, use January 2026 data instead
        $now = now();
        if ($now->year == 2025 && $now->month == 12) {
            $currentMonth = 1;
            $currentYear = 2026;
            $startDate = \Carbon\Carbon::create(2026, 1, 1)->startOfMonth();
            $endDate = \Carbon\Carbon::create(2026, 1, 31)->endOfMonth();
        } else {
            $currentMonth = $now->month;
            $currentYear = $now->year;
            $startDate = $now->startOfMonth();
            $endDate = $now->endOfMonth();
        }

        // Calculate current month income
        $clientPaymentIncome = \App\Models\ClientPayment::where(function($query) use ($currentMonth, $currentYear) {
            $query->where(function($q) use ($currentMonth, $currentYear) {
                $q->whereNotNull('payment_date')
                  ->whereMonth('payment_date', $currentMonth)
                  ->whereYear('payment_date', $currentYear);
            })->orWhere(function($q) use ($currentMonth, $currentYear) {
                $q->whereNull('payment_date')
                  ->whereMonth('created_at', $currentMonth)
                  ->whereYear('created_at', $currentYear);
            });
        })->where('amount_paid', '>', 0)->sum('amount_paid');
        
        $separateIncome = \App\Models\Income::whereMonth('income_date', $currentMonth)
            ->whereYear('income_date', $currentYear)
            ->sum('amount');
        
        $totalIncome = $clientPaymentIncome + $separateIncome;

        // Calculate planned expenses for current month
        $plannedExpenses = \App\Models\PlannedExpense::with('category')
            ->where('is_active', true)
            ->get()
            ->filter(function ($expense) use ($currentYear, $currentMonth) {
                return $expense->appliesToMonth($currentYear, $currentMonth);
            });
        $totalPlannedExpenses = $plannedExpenses->sum('amount');

        // Calculate actual expenses for current month
        // Regular expenses - count submitted, approved, and paid expenses
        $regularExpenses = \App\Models\Expense::whereMonth('expense_date', $currentMonth)
            ->whereYear('expense_date', $currentYear)
            ->whereIn('status', ['submitted', 'approved', 'paid'])
            ->sum('amount');

        // Developer payments (from payment history)
        $developerPayments = \App\Models\DeveloperPaymentHistory::whereMonth('payment_date', $currentMonth)
            ->whereYear('payment_date', $currentYear)
            ->sum('amount');

        // PM payments (from payment history)
        $pmPayments = \App\Models\PmPaymentHistory::whereMonth('payment_date', $currentMonth)
            ->whereYear('payment_date', $currentYear)
            ->sum('amount');

        // BD payments (from payment history)
        $bdPayments = \App\Models\BdPaymentHistory::whereMonth('payment_date', $currentMonth)
            ->whereYear('payment_date', $currentYear)
            ->sum('amount');

        // Vendor payments
        $vendorPayments = \App\Models\VendorPayment::whereMonth('payment_date', $currentMonth)
            ->whereYear('payment_date', $currentYear)
            ->sum('amount');

        // Advance payments (Khata)
        $advancePayments = \App\Models\AdvancePayment::whereMonth('payment_date', $currentMonth)
            ->whereYear('payment_date', $currentYear)
            ->sum('amount');

        $totalActualExpenses = $regularExpenses + $developerPayments + $pmPayments + $bdPayments + $vendorPayments + $advancePayments;
        $totalExpenses = $totalPlannedExpenses + $totalActualExpenses;

        // Calculate required amount and balance
        $requiredAmount = $totalExpenses;
        $incomeGap = $requiredAmount - $totalIncome;
        $currentBalance = $totalIncome - $totalActualExpenses; // Balance after actual expenses

        return [
            'leads' => [
                'total' => \App\Models\Lead::count(),
                'new' => $this->leadRepository->getByStatus('New')->count(),
                'in_progress' => $this->leadRepository->getByStatus('In Progress')->count(),
                'converted' => $this->leadRepository->getConverted()->count(),
                'lost' => $this->leadRepository->getByStatus('Lost')->count(),
            ],
            'clients' => [
                'total' => \App\Models\Client::count(),
                'active' => $this->clientRepository->getByStatus('Active')->count(),
                'inactive' => $this->clientRepository->getByStatus('Inactive')->count(),
            ],
            'projects' => [
                'total' => \App\Models\Project::count(),
                'by_status' => \App\Models\Project::select('status', DB::raw('count(*) as count'))
                    ->groupBy('status')
                    ->pluck('count', 'status'),
            ],
            'revenue' => [
                'total' => \App\Models\ClientPayment::sum('amount_paid') + \App\Models\Income::sum('amount'),
                'current_month' => $totalIncome,
                'pending' => $this->clientPaymentRepository->getPendingPayments()
                    ->sum('remaining_amount'),
            ],
            'financial' => [
                'income' => [
                    'total' => $totalIncome,
                    'from_client_payments' => $clientPaymentIncome,
                    'from_separate_income' => $separateIncome,
                ],
                'expenses' => [
                    'planned' => $totalPlannedExpenses,
                    'actual' => $totalActualExpenses,
                    'breakdown' => [
                        'regular_expenses' => $regularExpenses,
                        'developer_payments' => $developerPayments,
                        'pm_payments' => $pmPayments,
                        'bd_payments' => $bdPayments,
                        'vendor_payments' => $vendorPayments,
                        'advance_payments' => $advancePayments,
                    ],
                    'total' => $totalExpenses,
                ],
                'required_amount' => $requiredAmount,
                'income_gap' => $incomeGap,
                'current_balance' => $currentBalance,
                'is_sufficient' => $totalIncome >= $requiredAmount,
            ],
            'developer_balances' => $this->developerPaymentRepository->getOutstandingBalances()
                ->sum('remaining_amount'),
            'tasks_due_today' => $this->taskRepository->getDueToday()->count(),
            'leads_follow_up' => $this->leadRepository->getFollowUpToday()->count(),
        ];
    }

    public function getCharts()
    {
        // Monthly Revenue - Last 12 months (client payments + separate income)
        $clientPayments = \App\Models\ClientPayment::select(
                DB::raw('COALESCE(DATE_FORMAT(payment_date, "%Y-%m"), DATE_FORMAT(created_at, "%Y-%m")) as month'),
                DB::raw('COALESCE(DATE_FORMAT(payment_date, "%b %Y"), DATE_FORMAT(created_at, "%b %Y")) as month_label'),
                DB::raw('SUM(amount_paid) as total')
            )
            ->where(function($query) {
                $query->where(function($q) {
                    $q->whereNotNull('payment_date')
                      ->where('payment_date', '>=', now()->subMonths(12)->startOfMonth());
                })->orWhere(function($q) {
                    $q->whereNull('payment_date')
                      ->where('created_at', '>=', now()->subMonths(12)->startOfMonth());
                });
            })
            ->where('amount_paid', '>', 0)
            ->groupBy('month', 'month_label')
            ->get();
        
        $separateIncome = \App\Models\Income::select(
                DB::raw('DATE_FORMAT(income_date, "%Y-%m") as month'),
                DB::raw('DATE_FORMAT(income_date, "%b %Y") as month_label'),
                DB::raw('SUM(amount) as total')
            )
            ->where('income_date', '>=', now()->subMonths(12)->startOfMonth())
            ->groupBy('month', 'month_label')
            ->get();
        
        // Merge and combine by month
        $monthlyRevenue = collect();
        $allMonths = $clientPayments->pluck('month')->merge($separateIncome->pluck('month'))->unique();
        
        foreach ($allMonths as $month) {
            $clientTotal = $clientPayments->where('month', $month)->first()->total ?? 0;
            $incomeTotal = $separateIncome->where('month', $month)->first()->total ?? 0;
            $monthLabel = $clientPayments->where('month', $month)->first()->month_label 
                ?? $separateIncome->where('month', $month)->first()->month_label;
            
            $monthlyRevenue->push([
                'month' => $monthLabel,
                'total' => (float) $clientTotal + (float) $incomeTotal,
            ]);
        }
        
        $monthlyRevenue = $monthlyRevenue->sortBy('month')->values();

        // Lead Status Distribution
        $leadStatus = \App\Models\Lead::select('lead_status', DB::raw('count(*) as count'))
            ->groupBy('lead_status')
            ->get()
            ->map(function ($item) {
                return [
                    'name' => $item->lead_status,
                    'value' => (int) $item->count,
                ];
            });

        // Task Status Distribution
        $taskStatus = \App\Models\Task::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get()
            ->map(function ($item) {
                return [
                    'name' => $item->status,
                    'value' => (int) $item->count,
                ];
            });

        // BD Performance (Projects per BD)
        $bdPerformance = \App\Models\Project::select('assigned_bd', DB::raw('count(*) as count'))
            ->whereNotNull('assigned_bd')
            ->groupBy('assigned_bd')
            ->with('assignedBd:id,name')
            ->get()
            ->map(function ($item) {
                return [
                    'name' => $item->assignedBd->name ?? 'Unassigned',
                    'value' => (int) $item->count,
                ];
            });

        // Developer Task Distribution
        $developerTasks = \App\Models\Task::select('assigned_to', DB::raw('count(*) as count'))
            ->whereNotNull('assigned_to')
            ->groupBy('assigned_to')
            ->with('assignedUser:id,name')
            ->get()
            ->map(function ($item) {
                return [
                    'name' => $item->assignedUser->name ?? 'Unassigned',
                    'value' => (int) $item->count,
                ];
            });

        // Revenue by Month (Last 6 months) - client payments + separate income
        $clientPayments6Months = \App\Models\ClientPayment::select(
                DB::raw('COALESCE(DATE_FORMAT(payment_date, "%Y-%m"), DATE_FORMAT(created_at, "%Y-%m")) as month'),
                DB::raw('COALESCE(DATE_FORMAT(payment_date, "%b"), DATE_FORMAT(created_at, "%b")) as month_name'),
                DB::raw('SUM(amount_paid) as revenue')
            )
            ->where(function($query) {
                $query->where(function($q) {
                    $q->whereNotNull('payment_date')
                      ->where('payment_date', '>=', now()->subMonths(6)->startOfMonth());
                })->orWhere(function($q) {
                    $q->whereNull('payment_date')
                      ->where('created_at', '>=', now()->subMonths(6)->startOfMonth());
                });
            })
            ->where('amount_paid', '>', 0)
            ->groupBy('month', 'month_name')
            ->get();
        
        $separateIncome6Months = \App\Models\Income::select(
                DB::raw('DATE_FORMAT(income_date, "%Y-%m") as month'),
                DB::raw('DATE_FORMAT(income_date, "%b") as month_name'),
                DB::raw('SUM(amount) as revenue')
            )
            ->where('income_date', '>=', now()->subMonths(6)->startOfMonth())
            ->groupBy('month', 'month_name')
            ->get();
        
        // Merge and combine by month
        $revenueByMonth = collect();
        $allMonths6 = $clientPayments6Months->pluck('month')->merge($separateIncome6Months->pluck('month'))->unique();
        
        foreach ($allMonths6 as $month) {
            $clientRevenue = $clientPayments6Months->where('month', $month)->first()->revenue ?? 0;
            $incomeRevenue = $separateIncome6Months->where('month', $month)->first()->revenue ?? 0;
            $monthName = $clientPayments6Months->where('month', $month)->first()->month_name 
                ?? $separateIncome6Months->where('month', $month)->first()->month_name;
            
            $revenueByMonth->push([
                'month' => $monthName,
                'revenue' => (float) $clientRevenue + (float) $incomeRevenue,
            ]);
        }
        
        $revenueByMonth = $revenueByMonth->sortBy('month')->values();

        return [
            'monthly_revenue' => $monthlyRevenue,
            'lead_status' => $leadStatus,
            'task_status' => $taskStatus,
            'bd_performance' => $bdPerformance,
            'developer_tasks' => $developerTasks,
            'revenue_by_month' => $revenueByMonth,
        ];
    }
}

