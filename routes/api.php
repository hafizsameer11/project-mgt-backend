<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeveloperPaymentController;
use App\Http\Controllers\ClientPaymentController;
use App\Http\Controllers\PasswordVaultController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\TeamMemberController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\PaymentRequestController;
use App\Http\Controllers\GeneralRequestController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ProjectDocumentController;
use App\Http\Controllers\ProjectBdPaymentController;
use App\Http\Controllers\ProjectPmPaymentController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\VendorController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\VendorBillController;
use App\Http\Controllers\VendorPaymentController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\ChartOfAccountController;
use App\Http\Controllers\JournalEntryController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\FinancialReportController;
use App\Http\Controllers\RequirementController;
use App\Http\Controllers\ProjectPhaseController;
use App\Http\Controllers\ClientPortalController;
use App\Http\Controllers\NotificationController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);

    // Users
    Route::get('/users', [UserController::class, 'index']);

    // Dashboard
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
    Route::get('/dashboard/charts', [DashboardController::class, 'charts']);

    // Leads
    Route::apiResource('leads', LeadController::class);
    Route::post('/leads/{id}/convert', [LeadController::class, 'convertToClient']);
    Route::get('/leads/follow-up/reminders', [LeadController::class, 'followUpReminders']);

    // Clients
    Route::apiResource('clients', ClientController::class);
    Route::post('/clients/{id}/create-account', [ClientController::class, 'createUserAccount']);

    // Projects
    Route::apiResource('projects', ProjectController::class);

    // Tasks
    Route::apiResource('tasks', TaskController::class);
    Route::post('/tasks/{id}/timer/start', [TaskController::class, 'startTimer']);
    Route::get('/tasks/{id}/timer/active', [TaskController::class, 'getActiveTimer']);
    Route::post('/tasks/timer/{timerId}/pause', [TaskController::class, 'pauseTimer']);
    Route::post('/tasks/timer/{timerId}/resume', [TaskController::class, 'resumeTimer']);
    Route::post('/tasks/timer/{timerId}/stop', [TaskController::class, 'stopTimer']);

    // Developer Payments
    Route::get('/developer-payments', [DeveloperPaymentController::class, 'index']);
    Route::get('/developer-payments/{id}', [DeveloperPaymentController::class, 'show']);
    Route::post('/developer-payments', [DeveloperPaymentController::class, 'store']);
    Route::put('/developer-payments/{id}', [DeveloperPaymentController::class, 'update']);
    Route::post('/developer-payments/{id}/add-payment', [DeveloperPaymentController::class, 'addPayment']);
    Route::post('/developer-payments/{id}/mark-as-paid', [DeveloperPaymentController::class, 'markAsPaid']);
    Route::get('/developer-payments/{id}/invoice', [DeveloperPaymentController::class, 'generateInvoice']);
    Route::get('/developer-payments/{id}/invoice/download', [DeveloperPaymentController::class, 'downloadInvoice']);

    // Client Payments
    Route::get('/client-payments', [ClientPaymentController::class, 'index']);
    Route::get('/client-payments/{id}', [ClientPaymentController::class, 'show']);
    Route::post('/client-payments', [ClientPaymentController::class, 'store']);
    Route::put('/client-payments/{id}', [ClientPaymentController::class, 'update']);
    Route::get('/client-payments/{id}/invoice', [ClientPaymentController::class, 'generateInvoice']);
    Route::get('/client-payments/{id}/invoice/download', [ClientPaymentController::class, 'downloadInvoice']);

    // Password Vault
    Route::get('/password-vaults', [PasswordVaultController::class, 'index']);
    Route::post('/password-vaults', [PasswordVaultController::class, 'store']);
    Route::get('/password-vaults/{id}', [PasswordVaultController::class, 'show']);
    Route::put('/password-vaults/{id}', [PasswordVaultController::class, 'update']);
    Route::delete('/password-vaults/{id}', [PasswordVaultController::class, 'destroy']);

    // Teams
    Route::apiResource('teams', TeamController::class);

    // Activity Logs
    Route::get('/activity-logs', [ActivityLogController::class, 'index']);
    Route::get('/activity-logs/by-model', [ActivityLogController::class, 'getByModel']);

    // Team Member Dashboard
    Route::get('/team-member/dashboard', [TeamMemberController::class, 'dashboard']);
    Route::post('/teams/{id}/create-account', [TeamMemberController::class, 'createUserAccount']);

    // Leave Requests
    Route::apiResource('leave-requests', LeaveRequestController::class)->except(['destroy']);
    Route::put('/leave-requests/{id}/approve', [LeaveRequestController::class, 'update']);

    // Payment Requests
    Route::apiResource('payment-requests', PaymentRequestController::class)->except(['destroy']);
    Route::put('/payment-requests/{id}/approve', [PaymentRequestController::class, 'update']);

    // General Requests
    Route::apiResource('general-requests', GeneralRequestController::class)->except(['destroy']);
    Route::put('/general-requests/{id}/approve', [GeneralRequestController::class, 'update']);

    // Chat
    Route::get('/chat/conversations', [ChatController::class, 'getConversations']);
    Route::get('/chat/users', [ChatController::class, 'getUsers']);
    Route::get('/chat/project-chats', [ChatController::class, 'getProjectChats']);
    Route::get('/chat/messages', [ChatController::class, 'getMessages']);
    Route::post('/chat/send', [ChatController::class, 'sendMessage']);

    // Project Documents
    Route::get('/project-documents', [ProjectDocumentController::class, 'index']);
    Route::post('/project-documents', [ProjectDocumentController::class, 'store']);
    Route::get('/project-documents/{id}', [ProjectDocumentController::class, 'show']);
    Route::put('/project-documents/{id}', [ProjectDocumentController::class, 'update']);
    Route::delete('/project-documents/{id}', [ProjectDocumentController::class, 'destroy']);
    Route::get('/project-documents/{id}/download', [ProjectDocumentController::class, 'download']);

    // Project BD Payments
    Route::get('/project-bd-payments', [ProjectBdPaymentController::class, 'index']);
    Route::post('/project-bd-payments', [ProjectBdPaymentController::class, 'store']);
    Route::put('/project-bd-payments/{id}', [ProjectBdPaymentController::class, 'update']);
    Route::post('/project-bd-payments/{id}/add-payment', [ProjectBdPaymentController::class, 'addPayment']);
    Route::post('/project-bd-payments/{id}/mark-as-paid', [ProjectBdPaymentController::class, 'markAsPaid']);
    Route::get('/project-bd-payments/{id}/invoice', [ProjectBdPaymentController::class, 'generateInvoice']);
    Route::get('/project-bd-payments/{id}/invoice/download', [ProjectBdPaymentController::class, 'downloadInvoice']);

    // Project Manager Payments
    Route::get('/project-pm-payments', [ProjectPmPaymentController::class, 'index']);
    Route::get('/project-pm-payments/{id}', [ProjectPmPaymentController::class, 'show']);
    Route::post('/project-pm-payments', [ProjectPmPaymentController::class, 'store']);
    Route::put('/project-pm-payments/{id}', [ProjectPmPaymentController::class, 'update']);
    Route::post('/project-pm-payments/{id}/add-payment', [ProjectPmPaymentController::class, 'addPayment']);
    Route::post('/project-pm-payments/{id}/mark-as-paid', [ProjectPmPaymentController::class, 'markAsPaid']);
    Route::get('/project-pm-payments/{id}/invoice', [ProjectPmPaymentController::class, 'generateInvoice']);

            // Expense Management
            Route::apiResource('expenses', ExpenseController::class);
            Route::post('/expenses/{id}/submit', [ExpenseController::class, 'submit']);
            Route::post('/expenses/{id}/approve', [ExpenseController::class, 'approve']);
            Route::post('/expenses/{id}/reject', [ExpenseController::class, 'reject']);
            Route::apiResource('expense-categories', ExpenseCategoryController::class);
            
            // Planned Expenses
            Route::apiResource('planned-expenses', \App\Http\Controllers\PlannedExpenseController::class);
            Route::get('/planned-expenses/monthly-summary', [\App\Http\Controllers\PlannedExpenseController::class, 'monthlySummary']);
            
            // Income Management
            Route::apiResource('incomes', \App\Http\Controllers\IncomeController::class);

            // Vendor Management
            Route::apiResource('vendors', VendorController::class);

            // Attendance
            Route::apiResource('attendance', AttendanceController::class);
            Route::post('/attendance/clock-in', [AttendanceController::class, 'clockIn']);
            Route::post('/attendance/clock-out', [AttendanceController::class, 'clockOut']);

            // Purchase Orders
            Route::apiResource('purchase-orders', PurchaseOrderController::class);

            // Vendor Bills
            Route::apiResource('vendor-bills', VendorBillController::class);
            Route::post('/vendor-bills/{id}/approve', [VendorBillController::class, 'approve']);

            // Vendor Payments
            Route::apiResource('vendor-payments', VendorPaymentController::class);

            // Payroll
            Route::apiResource('payroll', PayrollController::class);
            Route::post('/payroll/{id}/process', [PayrollController::class, 'process']);

            // Chart of Accounts
            Route::apiResource('chart-of-accounts', ChartOfAccountController::class);

            // Journal Entries
            Route::apiResource('journal-entries', JournalEntryController::class);
            Route::post('/journal-entries/{id}/post', [JournalEntryController::class, 'post']);

            // Inventory
            Route::apiResource('inventory-items', InventoryController::class);
            Route::post('/inventory-items/{id}/adjust', [InventoryController::class, 'adjust']);

            // Assets
            Route::apiResource('assets', AssetController::class);
            Route::post('/assets/{id}/depreciate', [AssetController::class, 'depreciate']);

            // Financial Reports
            Route::get('/financial-reports/profit-loss', [FinancialReportController::class, 'profitLoss']);
            Route::get('/financial-reports/balance-sheet', [FinancialReportController::class, 'balanceSheet']);
            Route::get('/financial-reports/cash-flow', [FinancialReportController::class, 'cashFlow']);

            // Requirements Management
            Route::apiResource('requirements', RequirementController::class);
            Route::get('/requirements/{id}/download', [RequirementController::class, 'download']);

            // Project Phases
            Route::apiResource('project-phases', ProjectPhaseController::class);

            // Notifications
            Route::get('/notifications', [NotificationController::class, 'index']);
            Route::get('/notifications/unread', [NotificationController::class, 'unread']);
            Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
            Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);

            // Push Subscriptions (Web Push)
            Route::get('/push/public-key', [\App\Http\Controllers\PushSubscriptionController::class, 'getPublicKey']);
            Route::post('/push/subscribe', [\App\Http\Controllers\PushSubscriptionController::class, 'store']);
            Route::post('/push/unsubscribe', [\App\Http\Controllers\PushSubscriptionController::class, 'destroy']);
            
            // Push Subscriptions (Expo)
            Route::post('/push/expo-subscribe', [\App\Http\Controllers\PushSubscriptionController::class, 'storeExpo']);
            Route::post('/push/expo-unsubscribe', [\App\Http\Controllers\PushSubscriptionController::class, 'destroyExpo']);
        });

        // Client Portal Routes (accessible to clients)
        Route::middleware(['auth:sanctum'])->prefix('client-portal')->group(function () {
            Route::get('/dashboard', [ClientPortalController::class, 'dashboard']);
            Route::get('/projects', [ClientPortalController::class, 'projects']);
            Route::get('/projects/{id}', [ClientPortalController::class, 'project']);
            Route::get('/tasks', [ClientPortalController::class, 'tasks']);
            Route::get('/payments', [ClientPortalController::class, 'payments']);
            Route::get('/requirements', [ClientPortalController::class, 'requirements']);
            Route::post('/requirements', [ClientPortalController::class, 'createRequirement']);
            Route::get('/documents', [ClientPortalController::class, 'documents']);
            Route::post('/documents', [ClientPortalController::class, 'createDocument']);
        });
