<?php

namespace App\Services;

use App\Models\DeveloperPayment;
use App\Models\ClientPayment;
use App\Models\ProjectBdPayment;
use App\Models\ProjectPmPayment;
use Illuminate\Support\Facades\View;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class InvoiceService
{
    public function generateDeveloperInvoice(DeveloperPayment $payment): string
    {
        $payment->load('developer', 'project', 'paymentHistory');
        
        $invoiceNo = $payment->invoice_no ?? 'INV-DEV-' . str_pad($payment->id, 6, '0', STR_PAD_LEFT);
        
        // Update invoice number if not set
        if (!$payment->invoice_no) {
            $payment->update(['invoice_no' => $invoiceNo]);
        }
        
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
            'developer_name' => $payment->developer->full_name ?? 'N/A',
            'project_name' => $payment->project->title ?? 'N/A',
            'total_assigned' => $payment->total_assigned_amount ?? 0,
            'amount_paid' => $payment->amount_paid ?? 0,
            'remaining' => $payment->remaining_amount ?? 0,
            'payment_history' => $paymentHistory,
            'payment_period' => $payment->project->start_date && $payment->project->end_date 
                ? $payment->project->start_date->format('M d, Y') . ' - ' . $payment->project->end_date->format('M d, Y')
                : 'N/A',
        ];

        $html = View::make('invoices.developer', ['invoice' => $invoiceData])->render();
        
        return $this->generatePDF($html, $invoiceNo);
    }

    public function generateClientInvoice(ClientPayment $payment): string
    {
        $payment->load('client', 'project');
        
        $invoiceNo = $payment->invoice_no ?? 'INV-CLIENT-' . str_pad($payment->id, 6, '0', STR_PAD_LEFT);
        
        // Update invoice number if not set
        if (!$payment->invoice_no) {
            $payment->update(['invoice_no' => $invoiceNo]);
        }
        
        $invoiceData = [
            'invoice_no' => $invoiceNo,
            'date' => now()->format('Y-m-d'),
            'client_name' => $payment->client->name ?? 'N/A',
            'client_email' => $payment->client->email ?? '',
            'client_phone' => $payment->client->phone ?? '',
            'project_name' => $payment->project->title ?? 'N/A',
            'total_amount' => $payment->total_amount ?? 0,
            'amount_paid' => $payment->amount_paid ?? 0,
            'remaining' => $payment->remaining_amount ?? 0,
            'status' => $payment->status ?? 'Pending',
            'notes' => $payment->notes,
        ];

        $html = View::make('invoices.client', ['invoice' => $invoiceData])->render();
        
        return $this->generatePDF($html, $invoiceNo);
    }

    public function generatePDF(string $html, string $filename): string
    {
        // Ensure invoices directory exists
        $invoiceDir = storage_path('app/public/invoices');
        if (!file_exists($invoiceDir)) {
            mkdir($invoiceDir, 0755, true);
        }
        
        $pdf = Pdf::loadHTML($html);
        $pdf->setPaper('a4', 'portrait');
        
        $filePath = 'invoices/' . $filename . '_' . time() . '.pdf';
        $fullPath = storage_path('app/public/' . $filePath);
        
        $pdf->save($fullPath);
        
        return '/storage/' . $filePath;
    }
    
    public function generateBdPaymentInvoice(ProjectBdPayment $payment, string $invoiceNo = null, float $currentPaymentAmount = null, $paymentDate = null): string
    {
        $payment->load('bd', 'project', 'paymentHistory');
        
        $invoiceNo = $invoiceNo ?? $payment->invoice_no ?? 'INV-BD-' . str_pad($payment->id, 6, '0', STR_PAD_LEFT);
        
        $paymentHistory = $payment->paymentHistory->map(function ($item) {
            return [
                'date' => $item->payment_date->format('Y-m-d'),
                'amount' => $item->amount,
                'notes' => $item->notes,
            ];
        })->toArray();
        
        $invoiceData = [
            'invoice_no' => $invoiceNo,
            'date' => ($paymentDate ?? now())->format('Y-m-d'),
            'developer_name' => $payment->bd->name ?? 'N/A',
            'project_name' => $payment->project->title ?? 'N/A',
            'total_assigned' => $payment->calculated_amount ?? 0,
            'amount_paid' => $payment->amount_paid ?? 0,
            'remaining' => $payment->remaining_amount ?? 0,
            'payment_history' => $paymentHistory,
            'current_payment' => $currentPaymentAmount,
        ];

        $html = View::make('invoices.developer', ['invoice' => $invoiceData])->render();
        
        return $this->generatePDF($html, $invoiceNo);
    }

    public function generatePmPaymentInvoice(ProjectPmPayment $payment, string $invoiceNo = null, float $currentPaymentAmount = null, $paymentDate = null): string
    {
        $payment->load('pm', 'project', 'paymentHistory');
        
        $invoiceNo = $invoiceNo ?? 'INV-PM-' . str_pad($payment->id, 6, '0', STR_PAD_LEFT);
        
        $paymentHistory = $payment->paymentHistory->map(function ($item) {
            return [
                'date' => $item->payment_date->format('Y-m-d'),
                'amount' => $item->amount,
                'notes' => $item->notes,
            ];
        })->toArray();
        
        $invoiceData = [
            'invoice_no' => $invoiceNo,
            'date' => ($paymentDate ?? now())->format('Y-m-d'),
            'developer_name' => $payment->pm->name ?? 'N/A',
            'project_name' => $payment->project->title ?? 'N/A',
            'total_assigned' => $payment->calculated_amount ?? 0,
            'amount_paid' => $payment->amount_paid ?? 0,
            'remaining' => $payment->remaining_amount ?? 0,
            'payment_history' => $paymentHistory,
            'current_payment' => $currentPaymentAmount,
        ];

        $html = View::make('invoices.developer', ['invoice' => $invoiceData])->render();
        
        return $this->generatePDF($html, $invoiceNo);
    }
    
    public function downloadPDF(string $filePath)
    {
        $fullPath = storage_path('app/public/' . str_replace('/storage/', '', $filePath));
        
        if (!file_exists($fullPath)) {
            throw new \Exception('Invoice file not found');
        }
        
        return response()->download($fullPath);
    }
}

