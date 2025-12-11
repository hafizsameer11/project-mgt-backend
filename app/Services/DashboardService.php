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
                'total' => \App\Models\ClientPayment::sum('amount_paid'),
                'pending' => $this->clientPaymentRepository->getPendingPayments()
                    ->sum('remaining_amount'),
            ],
            'developer_balances' => $this->developerPaymentRepository->getOutstandingBalances()
                ->sum('remaining_amount'),
            'tasks_due_today' => $this->taskRepository->getDueToday()->count(),
            'leads_follow_up' => $this->leadRepository->getFollowUpToday()->count(),
        ];
    }

    public function getCharts()
    {
        // Monthly Revenue - Last 12 months
        $monthlyRevenue = \App\Models\ClientPayment::select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                DB::raw('DATE_FORMAT(created_at, "%b %Y") as month_label'),
                DB::raw('SUM(amount_paid) as total')
            )
            ->where('created_at', '>=', now()->subMonths(12))
            ->groupBy('month', 'month_label')
            ->orderBy('month')
            ->get()
            ->map(function ($item) {
                return [
                    'month' => $item->month_label,
                    'total' => (float) $item->total,
                ];
            });

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

        // Revenue by Month (Last 6 months)
        $revenueByMonth = \App\Models\ClientPayment::select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                DB::raw('DATE_FORMAT(created_at, "%b") as month_name'),
                DB::raw('SUM(amount_paid) as revenue')
            )
            ->where('created_at', '>=', now()->subMonths(6))
            ->groupBy('month', 'month_name')
            ->orderBy('month')
            ->get()
            ->map(function ($item) {
                return [
                    'month' => $item->month_name,
                    'revenue' => (float) $item->revenue,
                ];
            });

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

