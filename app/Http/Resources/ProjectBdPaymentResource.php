<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectBdPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'project' => new ProjectResource($this->whenLoaded('project')),
            'bd_id' => $this->bd_id,
            'bd' => new UserResource($this->whenLoaded('bd')),
            'payment_type' => $this->payment_type,
            'percentage' => $this->percentage,
            'fixed_amount' => $this->fixed_amount,
            'calculated_amount' => $this->calculated_amount,
            'amount_paid' => $this->amount_paid,
            'remaining_amount' => $this->remaining_amount,
            'payment_notes' => $this->payment_notes,
            'payment_history' => BdPaymentHistoryResource::collection($this->whenLoaded('paymentHistory')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

