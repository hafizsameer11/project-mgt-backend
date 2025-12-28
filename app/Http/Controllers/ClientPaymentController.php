<?php

namespace App\Http\Controllers;

use App\Services\ClientPaymentService;
use App\Services\InvoiceService;
use Illuminate\Http\Request;

class ClientPaymentController extends Controller
{
    protected $paymentService;
    protected $invoiceService;

    public function __construct(ClientPaymentService $paymentService, InvoiceService $invoiceService)
    {
        $this->paymentService = $paymentService;
        $this->invoiceService = $invoiceService;
    }

    public function index(Request $request)
    {
        $query = \App\Models\ClientPayment::query()->with('client', 'project');

        if ($request->has('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        if ($request->has('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $payments = $query->paginate(15);
        return response()->json($payments);
    }

    public function show(int $id)
    {
        $payment = \App\Models\ClientPayment::with('client', 'project')->find($id);
        if (!$payment) {
            return response()->json(['message' => 'Payment not found'], 404);
        }
        return response()->json($payment);
    }

    public function store(Request $request)
    {
        $request->validate([
            'client_id' => 'required|exists:clients,id',
            'project_id' => 'required|exists:projects,id',
            'invoice_no' => 'nullable|string|max:255',
            'total_amount' => 'nullable|numeric|min:0',
            'amount_paid' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $payment = $this->paymentService->create($request->validated(), $request->user()->id);
        return response()->json($payment->load('client', 'project'));
    }

    public function update(Request $request, int $id)
    {
        $request->validate([
            'amount_paid' => 'nullable|numeric|min:0',
            'payment_date' => 'nullable|date',
            'status' => 'nullable|in:Paid,Unpaid,Partial',
            'notes' => 'nullable|string',
        ]);

        $payment = $this->paymentService->update($id, $request->validated(), $request->user()->id);
        if (!$payment) {
            return response()->json(['message' => 'Payment not found'], 404);
        }
        return response()->json($payment->load('client', 'project'));
    }
    
    public function generateInvoice(int $id)
    {
        $payment = \App\Models\ClientPayment::find($id);
        if (!$payment) {
            return response()->json(['message' => 'Payment record not found'], 404);
        }

        $invoicePath = $this->invoiceService->generateClientInvoice($payment);
        $payment->refresh();

        return response()->json([
            'message' => 'Invoice generated successfully',
            'invoice_path' => $invoicePath,
            'invoice_no' => $payment->invoice_no,
            'download_url' => url($invoicePath),
            'payment' => $payment->load('client', 'project')
        ]);
    }
    
    public function downloadInvoice(int $id)
    {
        $payment = \App\Models\ClientPayment::find($id);
        if (!$payment) {
            return response()->json(['message' => 'Payment record not found'], 404);
        }

        // Generate invoice if not exists
        if (!$payment->invoice_no) {
            $invoicePath = $this->invoiceService->generateClientInvoice($payment);
        } else {
            // Find existing invoice file
            $invoiceDir = storage_path('app/public/invoices');
            $files = glob($invoiceDir . '/INV-CLIENT-' . str_pad($payment->id, 6, '0', STR_PAD_LEFT) . '_*.pdf');
            if (empty($files)) {
                $invoicePath = $this->invoiceService->generateClientInvoice($payment);
            } else {
                $invoicePath = '/storage/invoices/' . basename(end($files));
            }
        }

        return $this->invoiceService->downloadPDF($invoicePath);
    }
}

