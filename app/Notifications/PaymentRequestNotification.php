<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentRequestNotification extends Notification
{
    use Queueable;

    protected $paymentRequest;
    protected $action; // 'created', 'approved', 'rejected'

    public function __construct($paymentRequest, $action = 'created')
    {
        $this->paymentRequest = $paymentRequest;
        $this->action = $action;
    }

    public function via(object $notifiable): array
    {
        return ['database', \App\Notifications\Channels\PushChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'request_id' => $this->paymentRequest->id,
            'type' => $this->action === 'created' ? 'payment_request_created' : 'payment_request_' . $this->action,
            'status' => $this->paymentRequest->status,
            'amount' => $this->paymentRequest->amount,
            'team_member' => $this->paymentRequest->team->full_name ?? 'N/A',
        ];
    }

    public function toPush(object $notifiable): array
    {
        $teamMember = $this->paymentRequest->team->full_name ?? 'Team Member';
        $amount = number_format($this->paymentRequest->amount, 2);
        
        if ($this->action === 'created') {
            return [
                'title' => 'New Payment Request',
                'body' => $teamMember . ' has requested payment of $' . $amount,
                'icon' => '/icon-192x192.png',
                'badge' => '/icon-192x192.png',
                'data' => [
                    'url' => '/payment-requests',
                    'request_id' => $this->paymentRequest->id,
                    'type' => 'payment_request_created',
                ],
            ];
        } elseif ($this->action === 'approved') {
            return [
                'title' => 'Payment Request Approved',
                'body' => 'Your payment request of $' . $amount . ' has been approved',
                'icon' => '/icon-192x192.png',
                'badge' => '/icon-192x192.png',
                'data' => [
                    'url' => '/payment-requests',
                    'request_id' => $this->paymentRequest->id,
                    'type' => 'payment_request_approved',
                ],
            ];
        } else {
            return [
                'title' => 'Payment Request Rejected',
                'body' => 'Your payment request of $' . $amount . ' has been rejected' . ($this->paymentRequest->rejection_reason ? ': ' . substr($this->paymentRequest->rejection_reason, 0, 50) : ''),
                'icon' => '/icon-192x192.png',
                'badge' => '/icon-192x192.png',
                'data' => [
                    'url' => '/payment-requests',
                    'request_id' => $this->paymentRequest->id,
                    'type' => 'payment_request_rejected',
                ],
            ];
        }
    }
}
