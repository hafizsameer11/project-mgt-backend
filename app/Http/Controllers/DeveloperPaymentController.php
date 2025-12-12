<?php

namespace App\Http\Controllers;

use App\Services\DeveloperPaymentService;
use App\Services\InvoiceService;
use Illuminate\Http\Request;

class DeveloperPaymentController extends Controller
{
    protected $paymentService;
    protected $invoiceService;

    public function __construct(DeveloperPaymentService $paymentService, InvoiceService $invoiceService)
    {
        $this->paymentService = $paymentService;
        $this->invoiceService = $invoiceService;
    }

    public function index(Request $request)
    {
        $query = \App\Models\DeveloperPayment::query()->with('developer', 'project');

        if ($request->has('developer_id')) {
            $query->where('developer_id', $request->developer_id);
        }

        if ($request->has('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        $payments = $query->paginate(15);
        return response()->json($payments);
    }

    public function show(int $id)
    {
        $payment = \App\Models\DeveloperPayment::with('developer', 'project', 'paymentHistory')->find($id);
        if (!$payment) {
            return response()->json(['message' => 'Payment not found'], 404);
        }
        return response()->json($payment);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'developer_id' => 'required|exists:teams,id',
            'project_id' => 'required|exists:projects,id',
            'total_assigned_amount' => 'nullable|numeric|min:0',
            'payment_notes' => 'nullable|string',
        ]);

        $payment = $this->paymentService->create($validated, $request->user()->id);
        return response()->json($payment->load('developer', 'project'));
    }

    public function update(Request $request, int $id)
    {
        $validated = $request->validate([
            'total_assigned_amount' => 'nullable|numeric|min:0',
            'payment_notes' => 'nullable|string',
        ]);

        $payment = \App\Models\DeveloperPayment::find($id);
        if (!$payment) {
            return response()->json(['message' => 'Payment record not found'], 404);
        }

        $payment->update($validated);
        return response()->json($payment->load('developer', 'project'));
    }

    public function addPayment(Request $request, int $id)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
            'payment_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $payment = $this->paymentService->addPayment($id, $validated, $request->user()->id);
        if (!$payment) {
            return response()->json(['message' => 'Payment record not found'], 404);
        }
        return response()->json($payment);
    }

    public function generateInvoice(int $id)
    {
        $payment = \App\Models\DeveloperPayment::find($id);
        if (!$payment) {
            return response()->json(['message' => 'Payment record not found'], 404);
        }

        $invoicePath = $this->invoiceService->generateDeveloperInvoice($payment);
        $payment->refresh();

        return response()->json([
            'message' => 'Invoice generated successfully',
            'invoice_path' => $invoicePath,
            'invoice_no' => $payment->invoice_no,
            'download_url' => url($invoicePath),
            'payment' => $payment->load('developer', 'project')
        ]);
    }
    
    public function downloadInvoice(int $id)
    {
        $payment = \App\Models\DeveloperPayment::find($id);
        if (!$payment) {
            return response()->json(['message' => 'Payment record not found'], 404);
        }

        // Generate invoice if not exists
        if (!$payment->invoice_no) {
            $invoicePath = $this->invoiceService->generateDeveloperInvoice($payment);
        } else {
            // Find existing invoice file
            $invoiceDir = storage_path('app/public/invoices');
            $files = glob($invoiceDir . '/INV-DEV-' . str_pad($payment->id, 6, '0', STR_PAD_LEFT) . '_*.pdf');
            if (empty($files)) {
                $invoicePath = $this->invoiceService->generateDeveloperInvoice($payment);
            } else {
                $invoicePath = '/storage/invoices/' . basename(end($files));
            }
        }

        return $this->invoiceService->downloadPDF($invoicePath);
    }
}

