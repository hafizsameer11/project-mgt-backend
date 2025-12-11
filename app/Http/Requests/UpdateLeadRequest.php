<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:255',
            'source' => 'nullable|in:Facebook,Upwork,Fiverr,Website,Referral,Other',
            'estimated_budget' => 'nullable|numeric|min:0',
            'lead_status' => 'sometimes|in:New,In Progress,Converted,Lost',
            'assigned_to' => 'nullable|exists:users,id',
            'notes' => 'nullable|string',
            'follow_up_date' => 'nullable|date',
            'attachments' => 'nullable|array',
        ];
    }
}

