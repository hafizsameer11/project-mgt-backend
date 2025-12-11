<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'source' => $this->source,
            'estimated_budget' => $this->estimated_budget,
            'lead_status' => $this->lead_status,
            'assigned_to' => $this->assigned_to,
            'assigned_user' => new UserResource($this->whenLoaded('assignedUser')),
            'notes' => $this->notes,
            'follow_up_date' => $this->follow_up_date?->format('Y-m-d'),
            'attachments' => $this->attachments,
            'conversion_date' => $this->conversion_date?->format('Y-m-d'),
            'converted_client_id' => $this->converted_client_id,
            'project_id_after_conversion' => $this->project_id_after_conversion,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

