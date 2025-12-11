<?php

namespace App\Services;

use App\Repositories\DeveloperPaymentRepository;
use App\Repositories\ActivityLogRepository;
use App\Events\PaymentCompleted;
use Illuminate\Support\Facades\DB;

class DeveloperPaymentService
{
    protected $paymentRepository;
    protected $activityLogRepository;

    public function __construct(
        DeveloperPaymentRepository $paymentRepository,
        ActivityLogRepository $activityLogRepository
    ) {
        $this->paymentRepository = $paymentRepository;
        $this->activityLogRepository = $activityLogRepository;
    }

    public function create(array $data, int $userId)
    {
        $payment = $this->paymentRepository->create($data);
        $this->logActivity($payment, $userId, 'created', null, $data);
        return $payment;
    }

    public function addPayment(int $paymentId, array $data, int $userId)
    {
        return DB::transaction(function () use ($paymentId, $data, $userId) {
            $payment = $this->paymentRepository->find($paymentId);
            if (!$payment) {
                return null;
            }

            $oldAmount = $payment->amount_paid;
            $newAmount = $oldAmount + $data['amount'];

            $this->paymentRepository->update($paymentId, [
                'amount_paid' => $newAmount,
            ]);

            $payment->refresh();
            $payment->updateStatus();

            // Generate invoice for this payment
            $invoiceService = app(\App\Services\InvoiceService::class);
            $invoiceNo = 'INV-DEV-' . str_pad($payment->id, 6, '0', STR_PAD_LEFT) . '-' . str_pad(\App\Models\DeveloperPaymentHistory::where('developer_payment_id', $paymentId)->count() + 1, 3, '0', STR_PAD_LEFT);
            
            $invoicePath = $invoiceService->generateDeveloperInvoice($payment);

            $history = \App\Models\DeveloperPaymentHistory::create([
                'developer_payment_id' => $paymentId,
                'amount' => $data['amount'],
                'payment_date' => $data['payment_date'] ?? now(),
                'notes' => $data['notes'] ?? null,
                'invoice_path' => $invoicePath,
                'invoice_no' => $invoiceNo,
            ]);

            $this->logActivity($payment, $userId, 'payment_added', [
                'old_amount_paid' => $oldAmount,
            ], [
                'new_amount_paid' => $newAmount,
                'history_id' => $history->id,
            ]);

            event(new PaymentCompleted($payment, $history));

            return $payment->load('paymentHistory');
        });
    }

    public function generateInvoice(int $paymentId)
    {
        $payment = $this->paymentRepository->find($paymentId);
        if (!$payment) {
            return null;
        }

        $payment->load('developer', 'project');
        
        // Invoice generation logic will be handled in controller
        return $payment;
    }

    protected function logActivity($model, int $userId, string $action, $oldValue = null, $newValue = null)
    {
        $this->activityLogRepository->create([
            'model_type' => get_class($model),
            'model_id' => $model->id,
            'user_id' => $userId,
            'action' => $action,
            'old_value' => $oldValue,
            'new_value' => $newValue,
        ]);
    }
}

