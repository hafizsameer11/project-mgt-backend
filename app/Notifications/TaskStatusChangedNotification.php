<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskStatusChangedNotification extends Notification
{
    use Queueable;

    protected $task;
    protected $oldStatus;
    protected $newStatus;
    protected $changedBy;

    public function __construct($task, $oldStatus, $newStatus, $changedBy)
    {
        $this->task = $task;
        $this->oldStatus = $oldStatus;
        $this->newStatus = $newStatus;
        $this->changedBy = $changedBy;
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
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
            'changed_by_id' => $this->changedBy->id,
            'changed_by_name' => $this->changedBy->name,
            'type' => 'task_status_changed',
        ];
    }

    public function toExpoPush(object $notifiable): array
    {
        $statusMessages = [
            'Pending' => 'is pending',
            'In Progress' => 'is in progress',
            'Review' => 'is under review',
            'Completed' => 'has been completed',
        ];

        $message = $statusMessages[$this->newStatus] ?? 'status changed to ' . $this->newStatus;
        
        return [
            'title' => 'Task Status Changed',
            'body' => 'Task "' . $this->task->title . '" ' . $message . ' by ' . $this->changedBy->name,
            'data' => [
                'task_id' => $this->task->id,
                'type' => 'task_status_changed',
                'new_status' => $this->newStatus,
            ],
        ];
    }
}

