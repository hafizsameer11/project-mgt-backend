<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RequirementUpdatedNotification extends Notification
{
    use Queueable;

    protected $requirement;
    protected $updatedBy;

    public function __construct($requirement, $updatedBy)
    {
        $this->requirement = $requirement;
        $this->updatedBy = $updatedBy;
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
            'updated_by_id' => $this->updatedBy->id,
            'updated_by_name' => $this->updatedBy->name,
            'type' => 'requirement_updated',
        ];
    }

    public function toExpoPush(object $notifiable): array
    {
        $projectTitle = $this->requirement->project->title ?? 'the project';
        return [
            'title' => 'Requirement Updated',
            'body' => $this->updatedBy->name . ' updated requirement "' . $this->requirement->title . '" in project "' . $projectTitle . '"',
            'data' => [
                'requirement_id' => $this->requirement->id,
                'project_id' => $this->requirement->project_id,
                'type' => 'requirement_updated',
            ],
        ];
    }
}

