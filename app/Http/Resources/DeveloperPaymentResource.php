<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeveloperPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Ensure status is up to date
        if (!$this->status) {
            $this->updateStatus();
        }
        
        // Get client payment status for this project
        $clientPayment = \App\Models\ClientPayment::where('project_id', $this->project_id)->first();
        $clientPaymentStatus = 'Unpaid';
        
        if ($clientPayment) {
            if ($clientPayment->status === 'Paid' || ($clientPayment->remaining_amount <= 0 && $clientPayment->amount_paid > 0)) {
                $clientPaymentStatus = 'Fully Paid';
            } elseif ($clientPayment->amount_paid > 0 && $clientPayment->remaining_amount > 0) {
                $clientPaymentStatus = 'Partially Paid';
            }
        }
        
        return [
            'id' => $this->id,
            'developer_id' => $this->developer_id,
            'developer' => new TeamResource($this->whenLoaded('developer')),
            'project_id' => $this->project_id,
            'project' => new ProjectResource($this->whenLoaded('project')),
            'total_assigned_amount' => $this->total_assigned_amount,
            'amount_paid' => $this->amount_paid,
            'remaining_amount' => $this->remaining_amount,
            'status' => $this->status ?? 'Pending',
            'client_payment_status' => $clientPaymentStatus,
            'payment_notes' => $this->payment_notes,
            'invoice_no' => $this->invoice_no,
            'payment_history' => DeveloperPaymentHistoryResource::collection($this->whenLoaded('paymentHistory')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

