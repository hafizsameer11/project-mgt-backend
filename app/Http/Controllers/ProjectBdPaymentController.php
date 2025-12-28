<?php

namespace App\Http\Controllers;

use App\Models\ProjectBdPayment;
use App\Models\BdPaymentHistory;
use App\Services\FileUploadService;
use App\Services\InvoiceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectBdPaymentController extends Controller
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
        $query = ProjectBdPayment::query()->with('project', 'bd', 'paymentHistory');

        if ($request->has('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        if ($request->has('bd_id')) {
            $query->where('bd_id', $request->bd_id);
        }

        $payments = $query->paginate(15);
        return response()->json($payments);
    }

    public function store(Request $request)
    {
        $request->validate([
            'project_id' => 'required|exists:projects,id',
            'bd_id' => 'required|exists:users,id',
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

        $payment = ProjectBdPayment::create([
            'project_id' => $request->project_id,
            'bd_id' => $request->bd_id,
            'payment_type' => $request->payment_type,
            'percentage' => $request->percentage,
            'fixed_amount' => $request->fixed_amount,
            'calculated_amount' => $calculatedAmount,
            'amount_paid' => 0,
            'status' => 'Pending',
            'payment_notes' => $request->payment_notes,
        ]);

        return response()->json($payment->load('project', 'bd'), 201);
    }

    public function update(Request $request, int $id)
    {
        $request->validate([
            'payment_type' => 'sometimes|in:percentage,fixed_amount',
            'percentage' => 'nullable|numeric|min:0|max:100',
            'fixed_amount' => 'nullable|numeric|min:0',
            'payment_notes' => 'nullable|string',
        ]);

        $payment = ProjectBdPayment::find($id);
        if (!$payment) {
            return response()->json(['message' => 'Payment not found'], 404);
        }

        $payment->update($request->all());
        
        // Recalculate amount
        $payment->calculateAmount();
        $payment->save();

        return response()->json($payment->load('project', 'bd'));
    }

    public function addPayment(Request $request, int $id)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0',
            'payment_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($request, $id) {
            $payment = ProjectBdPayment::with('project', 'bd')->find($id);
            if (!$payment) {
                return response()->json(['message' => 'Payment not found'], 404);
            }

            $oldAmount = $payment->amount_paid;
            $newAmount = $oldAmount + $request->amount;

            $payment->update(['amount_paid' => $newAmount]);
            $payment->updateStatus();

            // Generate invoice for this payment
            $invoiceNo = 'INV-BD-' . str_pad($payment->id, 6, '0', STR_PAD_LEFT) . '-' . str_pad(BdPaymentHistory::where('bd_payment_id', $id)->count() + 1, 3, '0', STR_PAD_LEFT);
            
            $invoicePath = $this->invoiceService->generateBdPaymentInvoice($payment, $invoiceNo, $request->amount, $request->payment_date ?? now());

            $history = BdPaymentHistory::create([
                'bd_payment_id' => $id,
                'amount' => $request->amount,
                'payment_date' => $request->payment_date ?? now(),
                'notes' => $request->notes,
                'invoice_path' => $invoicePath,
                'invoice_no' => $invoiceNo,
            ]);

            return response()->json($payment->load('project', 'bd', 'paymentHistory'));
        });
    }

    public function markAsPaid(Request $request, int $id)
    {
        $request->validate([
            'payment_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($request, $id) {
            $payment = ProjectBdPayment::with('project', 'bd')->find($id);
            if (!$payment) {
                return response()->json(['message' => 'BD Payment not found'], 404);
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
            $invoiceNo = 'INV-BD-' . str_pad($payment->id, 6, '0', STR_PAD_LEFT) . '-' . str_pad(BdPaymentHistory::where('bd_payment_id', $id)->count() + 1, 3, '0', STR_PAD_LEFT);
            
            $paymentDate = $request->payment_date ?? now();
            $invoicePath = $this->invoiceService->generateBdPaymentInvoice($payment, $invoiceNo, $remainingAmount, $paymentDate);

            $history = BdPaymentHistory::create([
                'bd_payment_id' => $id,
                'amount' => $remainingAmount,
                'payment_date' => $paymentDate,
                'notes' => $request->notes ?? 'Marked as paid',
                'invoice_path' => $invoicePath,
            ]);

            return response()->json([
                'message' => 'Payment marked as paid successfully',
                'payment' => $payment->load('project', 'bd', 'paymentHistory')
            ]);
        });
    }

    public function generateInvoice(int $id)
    {
        $payment = ProjectBdPayment::with('project', 'bd', 'paymentHistory')->find($id);
        if (!$payment) {
            return response()->json(['message' => 'Payment not found'], 404);
        }

        // Use developer invoice template for BD payments
        $invoiceNo = 'INV-BD-' . str_pad($payment->id, 6, '0', STR_PAD_LEFT);
        
        $paymentHistory = $payment->paymentHistory->map(function ($item) {
            return [
                'date' => $item->payment_date->format('Y-m-d'),
                'amount' => $item->amount,
                'notes' => $item->notes,
            ];
        })->toArray();
        
        $invoiceData = [
            'invoice_no' => $invoiceNo,
            'date' => now()->format('Y-m-d'),
            'developer_name' => $payment->bd->name ?? 'N/A',
            'project_name' => $payment->project->title ?? 'N/A',
            'total_assigned' => $payment->calculated_amount ?? 0,
            'amount_paid' => $payment->amount_paid ?? 0,
            'remaining' => $payment->remaining_amount ?? 0,
            'payment_history' => $paymentHistory,
            'payment_period' => $payment->project->start_date && $payment->project->end_date 
                ? $payment->project->start_date->format('M d, Y') . ' - ' . $payment->project->end_date->format('M d, Y')
                : 'N/A',
        ];

        $html = \Illuminate\Support\Facades\View::make('invoices.developer', ['invoice' => $invoiceData])->render();
        $invoicePath = $this->invoiceService->generatePDF($html, $invoiceNo);

        return response()->json([
            'message' => 'Invoice generated successfully',
            'invoice_path' => $invoicePath,
            'invoice_no' => $invoiceNo,
            'download_url' => url($invoicePath),
            'payment' => $payment,
        ]);
    }
    
    public function downloadInvoice(int $id)
    {
        $payment = ProjectBdPayment::find($id);
        if (!$payment) {
            return response()->json(['message' => 'Payment not found'], 404);
        }

        $invoiceNo = 'INV-BD-' . str_pad($payment->id, 6, '0', STR_PAD_LEFT);
        $invoiceDir = storage_path('app/public/invoices');
        $files = glob($invoiceDir . '/' . $invoiceNo . '_*.pdf');
        
        if (empty($files)) {
            // Generate invoice first
            $this->generateInvoice($id);
            $files = glob($invoiceDir . '/' . $invoiceNo . '_*.pdf');
        }
        
        if (!empty($files)) {
            $invoicePath = '/storage/invoices/' . basename(end($files));
            return $this->invoiceService->downloadPDF($invoicePath);
        }
        
        return response()->json(['message' => 'Invoice not found'], 404);
    }
}

