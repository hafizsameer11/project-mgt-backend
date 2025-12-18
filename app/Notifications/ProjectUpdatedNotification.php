<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ProjectUpdatedNotification extends Notification
{
    use Queueable;

    protected $project;
    protected $updatedBy;

    public function __construct($project, $updatedBy)
    {
        $this->project = $project;
        $this->updatedBy = $updatedBy;
    }

    public function via(object $notifiable): array
    {
        return ['database', \App\Notifications\Channels\ExpoPushChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'project_id' => $this->project->id,
            'project_title' => $this->project->title,
            'updated_by_id' => $this->updatedBy->id,
            'updated_by_name' => $this->updatedBy->name,
            'type' => 'project_updated',
        ];
    }

    public function toExpoPush(object $notifiable): array
    {
        return [
            'title' => 'Project Updated',
            'body' => $this->updatedBy->name . ' updated project "' . $this->project->title . '"',
            'data' => [
                'project_id' => $this->project->id,
                'type' => 'project_updated',
            ],
        ];
    }
}

