<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskStartedNotification extends Notification
{
    use Queueable;

    protected $task;
    protected $user;

    public function __construct($task, $user)
    {
        $this->task = $task;
        $this->user = $user;
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
            'user_id' => $this->user->id,
            'user_name' => $this->user->name,
            'type' => 'task_started',
        ];
    }

    public function toExpoPush(object $notifiable): array
    {
        return [
            'title' => 'Task Started',
            'body' => $this->user->name . ' started working on task "' . $this->task->title . '"',
            'data' => [
                'task_id' => $this->task->id,
                'type' => 'task_started',
                'user_id' => $this->user->id,
            ],
        ];
    }
}

