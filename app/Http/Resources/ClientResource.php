<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Check if client has a user account
        $hasAccount = false;
        if ($this->email) {
            $hasAccount = \App\Models\User::where('email', $this->email)
                ->where('role', 'Client')
                ->exists();
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'company' => $this->company,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'website' => $this->website,
            'notes' => $this->notes,
            'assigned_bd' => $this->assigned_bd,
            'assigned_bd_user' => new UserResource($this->whenLoaded('assignedBd')),
            'status' => $this->status,
            'has_account' => $hasAccount,
            'projects' => ProjectResource::collection($this->whenLoaded('projects')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

