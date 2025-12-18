<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ProjectCreatedNotification extends Notification
{
    use Queueable;

    protected $project;
    protected $createdBy;

    public function __construct($project, $createdBy)
    {
        $this->project = $project;
        $this->createdBy = $createdBy;
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
            'created_by_id' => $this->createdBy->id,
            'created_by_name' => $this->createdBy->name,
            'type' => 'project_created',
        ];
    }

    public function toExpoPush(object $notifiable): array
    {
        return [
            'title' => 'New Project Created',
            'body' => $this->createdBy->name . ' created a new project "' . $this->project->title . '"',
            'data' => [
                'project_id' => $this->project->id,
                'type' => 'project_created',
            ],
        ];
    }
}

