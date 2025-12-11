<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BdPaymentHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'bd_payment_id' => $this->bd_payment_id,
            'amount' => $this->amount,
            'payment_date' => $this->payment_date?->format('Y-m-d'),
            'notes' => $this->notes,
            'invoice_path' => $this->invoice_path,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

