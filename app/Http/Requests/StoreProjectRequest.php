<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'client_id' => 'nullable|exists:clients,id',
            'budget' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'project_type' => 'nullable|string|max:255',
            'priority' => 'nullable|in:Low,Medium,High,Critical',
            'status' => 'required|in:Planning,In Progress,On Hold,Completed,Cancelled',
            'assigned_bd' => 'nullable|exists:users,id',
            'tags' => 'nullable|array',
            'repo_link' => 'nullable|url|max:255',
            'server_url' => 'nullable|url|max:255',
            'team_ids' => 'nullable|array',
            'team_ids.*' => 'exists:teams,id',
        ];
    }
}

