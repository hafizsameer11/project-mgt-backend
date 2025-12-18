<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskCreatedNotification extends Notification
{
    use Queueable;

    protected $task;

    public function __construct($task)
    {
        $this->task = $task;
    }

    public function via(object $notifiable): array
    {
        return ['database', \App\Notifications\Channels\PushChannel::class, \App\Notifications\Channels\ExpoPushChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'task_id' => $this->task->id,
            'task_title' => $this->task->title,
            'type' => 'task_created',
        ];
    }

    public function toPush(object $notifiable): array
    {
        return [
            'title' => 'New Task Created',
            'body' => 'A new task "' . $this->task->title . '" has been created.',
            'icon' => '/icon-192x192.png',
            'badge' => '/icon-192x192.png',
            'data' => [
                'url' => '/tasks',
                'task_id' => $this->task->id,
                'type' => 'task_created',
            ],
        ];
    }

    public function toExpoPush(object $notifiable): array
    {
        return [
            'title' => 'New Task Created',
            'body' => 'A new task "' . $this->task->title . '" has been created.',
            'data' => [
                'task_id' => $this->task->id,
                'type' => 'task_created',
            ],
        ];
    }
}
