<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskUpdatedNotification extends Notification
{
    use Queueable;

    protected $task;
    protected $updatedBy;

    public function __construct($task, $updatedBy)
    {
        $this->task = $task;
        $this->updatedBy = $updatedBy;
    }

    public function via(object $notifiable): array
    {
        return ['database', \App\Notifications\Channels\ExpoPushChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'task_id' => $this->task->id,
            'task_title' => $this->task->title,
            'updated_by_id' => $this->updatedBy->id,
            'updated_by_name' => $this->updatedBy->name,
            'type' => 'task_updated',
        ];
    }

    public function toExpoPush(object $notifiable): array
    {
        return [
            'title' => 'Task Updated',
            'body' => $this->updatedBy->name . ' updated task "' . $this->task->title . '"',
            'data' => [
                'task_id' => $this->task->id,
                'type' => 'task_updated',
            ],
        ];
    }
}

