<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskDeletedNotification extends Notification
{
    use Queueable;

    protected $taskTitle;
    protected $deletedBy;

    public function __construct($taskTitle, $deletedBy)
    {
        $this->taskTitle = $taskTitle;
        $this->deletedBy = $deletedBy;
    }

    public function via(object $notifiable): array
    {
        return ['database', \App\Notifications\Channels\ExpoPushChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'task_title' => $this->taskTitle,
            'deleted_by_id' => $this->deletedBy->id,
            'deleted_by_name' => $this->deletedBy->name,
            'type' => 'task_deleted',
        ];
    }

    public function toExpoPush(object $notifiable): array
    {
        return [
            'title' => 'Task Deleted',
            'body' => 'Task "' . $this->taskTitle . '" has been deleted by ' . $this->deletedBy->name,
            'data' => [
                'type' => 'task_deleted',
            ],
        ];
    }
}

