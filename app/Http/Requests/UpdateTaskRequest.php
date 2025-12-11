<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'project_id' => 'nullable|exists:projects,id',
            'assigned_to' => 'nullable|exists:users,id',
            'priority' => 'nullable|in:Low,Medium,High,Critical',
            'status' => 'sometimes|in:Pending,In Progress,Completed',
            'estimated_hours' => 'nullable|numeric|min:0',
            'actual_time' => 'nullable|numeric|min:0',
            'deadline' => 'nullable|date',
            'task_type' => 'nullable|in:Today,Tomorrow,Next 2–3 Days,This Week,Next Week',
        ];
    }
}

