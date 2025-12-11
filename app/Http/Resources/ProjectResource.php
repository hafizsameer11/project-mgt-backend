<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isDeveloper = $user && ($user->role === 'Developer' || $user->role === 'Project Manager');
        
        $data = [
            'id' => $this->id,
            'title' => $this->title,
            'client_id' => $this->client_id,
            'client' => new ClientResource($this->whenLoaded('client')),
            'description' => $this->description,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'project_type' => $this->project_type,
            'priority' => $this->priority,
            'status' => $this->status,
            'assigned_bd' => $this->assigned_bd,
            'assigned_bd_user' => new UserResource($this->whenLoaded('assignedBd')),
            'attachments' => $this->attachments,
            'tags' => $this->tags,
            'repo_link' => $this->repo_link,
            'server_url' => $this->server_url,
            'teams' => TeamResource::collection($this->whenLoaded('teams')),
            'tasks' => TaskResource::collection($this->whenLoaded('tasks')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
        
        // Hide budget from developers
        if (!$isDeveloper) {
            $data['budget'] = $this->budget;
        }
        
        return $data;
    }
}

