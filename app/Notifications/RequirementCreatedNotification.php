<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RequirementCreatedNotification extends Notification
{
    use Queueable;

    protected $requirement;
    protected $createdBy;

    public function __construct($requirement, $createdBy)
    {
        $this->requirement = $requirement;
        $this->createdBy = $createdBy;
    }

    public function via(object $notifiable): array
    {
        return ['database', \App\Notifications\Channels\ExpoPushChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'requirement_id' => $this->requirement->id,
            'requirement_title' => $this->requirement->title,
            'project_id' => $this->requirement->project_id,
            'project_title' => $this->requirement->project->title ?? 'N/A',
            'created_by_id' => $this->createdBy->id,
            'created_by_name' => $this->createdBy->name,
            'type' => 'requirement_created',
        ];
    }

    public function toExpoPush(object $notifiable): array
    {
        $projectTitle = $this->requirement->project->title ?? 'the project';
        return [
            'title' => 'New Requirement Added',
            'body' => $this->createdBy->name . ' added requirement "' . $this->requirement->title . '" to project "' . $projectTitle . '"',
            'data' => [
                'requirement_id' => $this->requirement->id,
                'project_id' => $this->requirement->project_id,
                'type' => 'requirement_created',
            ],
        ];
    }
}

