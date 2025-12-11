<?php

namespace App\Services;

use App\Repositories\ClientPaymentRepository;
use App\Repositories\ActivityLogRepository;
use App\Events\PaymentCompleted;

class ClientPaymentService
{
    protected $paymentRepository;
    protected $activityLogRepository;

    public function __construct(
        ClientPaymentRepository $paymentRepository,
        ActivityLogRepository $activityLogRepository
    ) {
        $this->paymentRepository = $paymentRepository;
        $this->activityLogRepository = $activityLogRepository;
    }

    public function create(array $data, int $userId)
    {
        $data['remaining_amount'] = ($data['total_amount'] ?? 0) - ($data['amount_paid'] ?? 0);
        
        if ($data['remaining_amount'] <= 0) {
            $data['status'] = 'Paid';
        } elseif ($data['amount_paid'] > 0) {
            $data['status'] = 'Partial';
        } else {
            $data['status'] = 'Unpaid';
        }

        $payment = $this->paymentRepository->create($data);
        $this->logActivity($payment, $userId, 'created', null, $data);

        if ($payment->status === 'Paid') {
            event(new PaymentCompleted($payment, null));
        }

        return $payment;
    }

    public function update(int $id, array $data, int $userId)
    {
        $payment = $this->paymentRepository->find($id);
        if (!$payment) {
            return null;
        }

        if (isset($data['amount_paid'])) {
            $data['remaining_amount'] = ($payment->total_amount ?? 0) - $data['amount_paid'];
            
            if ($data['remaining_amount'] <= 0) {
                $data['status'] = 'Paid';
            } elseif ($data['amount_paid'] > 0) {
                $data['status'] = 'Partial';
            } else {
                $data['status'] = 'Unpaid';
            }
        }

        $oldData = $payment->toArray();
        $this->paymentRepository->update($id, $data);
        $payment->refresh();

        $this->logActivity($payment, $userId, 'updated', $oldData, $payment->toArray());

        if ($payment->status === 'Paid' && $oldData['status'] !== 'Paid') {
            event(new PaymentCompleted($payment, null));
        }

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

