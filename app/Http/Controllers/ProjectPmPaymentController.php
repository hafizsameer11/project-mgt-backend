<?php

namespace App\Http\Controllers;

use App\Models\ProjectPmPayment;
use App\Models\PmPaymentHistory;
use App\Services\FileUploadService;
use App\Services\InvoiceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectPmPaymentController extends Controller
{
    protected $fileUploadService;
    protected $invoiceService;

    public function __construct(FileUploadService $fileUploadService, InvoiceService $invoiceService)
    {
        $this->fileUploadService = $fileUploadService;
        $this->invoiceService = $invoiceService;
    }

    public function index(Request $request)
    {
        $query = ProjectPmPayment::query()->with('project', 'pm', 'paymentHistory');

        if ($request->has('project_id')) {
            $query->where('project_id', $request->project_id);
        }
        if ($request->has('pm_id')) {
            $query->where('pm_id', $request->pm_id);
        }

        $payments = $query->paginate(15);
        return response()->json($payments);
    }

    public function store(Request $request)
    {
        $request->validate([
            'project_id' => 'required|exists:projects,id',
            'pm_id' => 'required|exists:users,id',
            'payment_type' => 'required|in:percentage,fixed_amount',
            'percentage' => 'nullable|numeric|min:0|max:100',
            'fixed_amount' => 'nullable|numeric|min:0',
            'payment_notes' => 'nullable|string',
        ]);

        $project = \App\Models\Project::find($request->project_id);
        if (!$project) {
            return response()->json(['message' => 'Project not found'], 404);
        }

        $calculatedAmount = 0;
        if ($request->payment_type === 'percentage' && $request->percentage !== null) {
            $calculatedAmount = ($project->budget * $request->percentage) / 100;
        } elseif ($request->payment_type === 'fixed_amount' && $request->fixed_amount !== null) {
            $calculatedAmount = $request->fixed_amount;
        }

        $payment = ProjectPmPayment::create([
            'project_id' => $request->project_id,
            'pm_id' => $request->pm_id,
            'payment_type' => $request->payment_type,
            'percentage' => $request->percentage,
            'fixed_amount' => $request->fixed_amount,
            'calculated_amount' => $calculatedAmount,
            'amount_paid' => 0,
            'status' => 'Pending',
            'payment_notes' => $request->payment_notes,
        ]);

        return response()->json($payment->load('project', 'pm'), 201);
    }

    public function update(Request $request, int $id)
    {
        $payment = ProjectPmPayment::find($id);
        if (!$payment) {
            return response()->json(['message' => 'PM Payment not found'], 404);
        }

        $request->validate([
            'payment_type' => 'sometimes|in:percentage,fixed_amount',
            'percentage' => 'nullable|numeric|min:0|max:100',
            'fixed_amount' => 'nullable|numeric|min:0',
            'payment_notes' => 'nullable|string',
        ]);

        $data = $request->only(['payment_type', 'percentage', 'fixed_amount', 'payment_notes']);

        // Recalculate amount if type or values change
        if (isset($data['payment_type']) || isset($data['percentage']) || isset($data['fixed_amount'])) {
            $project = $payment->project;
            $newCalculatedAmount = 0;
            $currentPaymentType = $data['payment_type'] ?? $payment->payment_type;
            $currentPercentage = $data['percentage'] ?? $payment->percentage;
            $currentFixedAmount = $data['fixed_amount'] ?? $payment->fixed_amount;

            if ($currentPaymentType === 'percentage' && $currentPercentage !== null) {
                $newCalculatedAmount = ($project->budget * $currentPercentage) / 100;
            } elseif ($currentPaymentType === 'fixed_amount' && $currentFixedAmount !== null) {
                $newCalculatedAmount = $currentFixedAmount;
            }
            $data['calculated_amount'] = $newCalculatedAmount;
        }

        $payment->update($data);

        return response()->json($payment->load('project', 'pm'));
    }

    public function addPayment(Request $request, int $id)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0',
            'payment_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($request, $id) {
            $payment = ProjectPmPayment::with('project', 'pm')->find($id);
            if (!$payment) {
                return response()->json(['message' => 'PM Payment not found'], 404);
            }

            $oldAmount = $payment->amount_paid;
            $newAmount = $oldAmount + $request->amount;

            if ($newAmount > $payment->calculated_amount) {
                return response()->json(['message' => 'Payment amount exceeds calculated amount'], 422);
            }

            $payment->update(['amount_paid' => $newAmount]);
            $payment->updateStatus();

            // Generate invoice for this payment
            $invoiceNo = 'INV-PM-' . str_pad($payment->id, 6, '0', STR_PAD_LEFT) . '-' . str_pad(PmPaymentHistory::where('project_pm_payment_id', $id)->count() + 1, 3, '0', STR_PAD_LEFT);
            
            $invoicePath = $this->invoiceService->generatePmPaymentInvoice($payment, $invoiceNo, $request->amount, $request->payment_date ?? now());

            $history = PmPaymentHistory::create([
                'project_pm_payment_id' => $id,
                'amount' => $request->amount,
                'payment_date' => $request->payment_date ?? now(),
                'notes' => $request->notes,
                'invoice_path' => $invoicePath,
                'invoice_no' => $invoiceNo,
            ]);

            return response()->json($payment->load('project', 'pm', 'paymentHistory'));
        });
    }

    public function markAsPaid(Request $request, int $id)
    {
        $request->validate([
            'payment_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($request, $id) {
            $payment = ProjectPmPayment::with('project', 'pm')->find($id);
            if (!$payment) {
                return response()->json(['message' => 'PM Payment not found'], 404);
            }

            $remainingAmount = $payment->remaining_amount;
            if ($remainingAmount <= 0) {
                return response()->json(['message' => 'Payment is already fully paid'], 422);
            }

            $oldAmount = $payment->amount_paid;
            $newAmount = $oldAmount + $remainingAmount;

            $payment->update(['amount_paid' => $newAmount]);
            $payment->updateStatus();

            // Generate invoice for this payment
            $invoiceNo = 'INV-PM-' . str_pad($payment->id, 6, '0', STR_PAD_LEFT) . '-' . str_pad(PmPaymentHistory::where('project_pm_payment_id', $id)->count() + 1, 3, '0', STR_PAD_LEFT);
            
            $paymentDate = $request->payment_date ?? now();
            $invoicePath = $this->invoiceService->generatePmPaymentInvoice($payment, $invoiceNo, $remainingAmount, $paymentDate);

            $history = PmPaymentHistory::create([
                'project_pm_payment_id' => $id,
                'amount' => $remainingAmount,
                'payment_date' => $paymentDate,
                'notes' => $request->notes ?? 'Marked as paid',
                'invoice_path' => $invoicePath,
                'invoice_no' => $invoiceNo,
            ]);

            return response()->json([
                'message' => 'Payment marked as paid successfully',
                'payment' => $payment->load('project', 'pm', 'paymentHistory')
            ]);
        });
    }

    public function generateInvoice(int $id)
    {
        $payment = ProjectPmPayment::with('project', 'pm', 'paymentHistory')->find($id);
        if (!$payment) {
            return response()->json(['message' => 'PM Payment record not found'], 404);
        }

        $invoiceNo = $payment->invoice_no ?? 'INV-PM-' . str_pad($payment->id, 6, '0', STR_PAD_LEFT);
        
        if (!$payment->invoice_no) {
            $payment->update(['invoice_no' => $invoiceNo]);
        }

        $invoicePath = $this->invoiceService->generatePmPaymentInvoice($payment, $invoiceNo);
        
        return response()->json([
            'message' => 'Invoice generated',
            'download_url' => url($invoicePath),
        ]);
    }
}
