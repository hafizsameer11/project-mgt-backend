<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'project_id' => $this->project_id,
            'project' => new ProjectResource($this->whenLoaded('project')),
            'assigned_to' => $this->assigned_to,
            'assigned_user' => new UserResource($this->whenLoaded('assignedUser')),
            'created_by' => $this->created_by,
            'creator' => new UserResource($this->whenLoaded('creator')),
            'priority' => $this->priority,
            'status' => $this->status,
            'estimated_hours' => $this->estimated_hours,
            'actual_time' => $this->actual_time,
            'deadline' => $this->deadline?->format('Y-m-d'),
            'attachments' => $this->attachments,
            'task_type' => $this->task_type,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

