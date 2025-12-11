<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentReceivedNotification extends Notification
{
    use Queueable;

    protected $payment;
    protected $type; // 'developer' or 'client'

    /**
     * Create a new notification instance.
     */
    public function __construct($payment, $type = 'developer')
    {
        $this->payment = $payment;
        $this->type = $type;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $amount = $this->type === 'developer' 
            ? $this->payment->amount_paid 
            : $this->payment->amount_paid;
        
        $projectName = $this->payment->project->title ?? 'N/A';
        
        return (new MailMessage)
                    ->subject('Payment Received - ' . $projectName)
                    ->line('A payment has been received.')
                    ->line('Amount: $' . number_format($amount, 2))
                    ->line('Project: ' . $projectName)
                    ->action('View Payment', url('/payments'))
                    ->line('Thank you for using our application!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'payment_id' => $this->payment->id,
            'amount' => $this->payment->amount_paid,
            'type' => $this->type,
        ];
    }
}
