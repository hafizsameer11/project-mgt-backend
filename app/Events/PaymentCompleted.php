<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $payment;
    public $history;

    public function __construct($payment, $history = null)
    {
        $this->payment = $payment;
        $this->history = $history;
    }
}

