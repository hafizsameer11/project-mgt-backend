<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskAssignedNotification extends Notification
{
    use Queueable;

    protected $task;

    /**
     * Create a new notification instance.
     */
    public function __construct($task)
    {
        $this->task = $task;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail', \App\Notifications\Channels\PushChannel::class];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
                    ->subject('New Task Assigned to You')
                    ->line('A new task has been assigned to you.')
                    ->line('Task: ' . $this->task->title)
                    ->line('Project: ' . ($this->task->project->title ?? 'N/A'))
                    ->line('Priority: ' . ($this->task->priority ?? 'N/A'))
                    ->action('View Task', url('/tasks/' . $this->task->id))
                    ->line('Thank you for using our application!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'task_id' => $this->task->id,
            'task_title' => $this->task->title,
            'type' => 'task_assigned',
        ];
    }

    public function toPush(object $notifiable): array
    {
        return [
            'title' => 'New Task Assigned',
            'body' => 'A new task "' . $this->task->title . '" has been assigned to you.',
            'icon' => '/icon-192x192.png',
            'badge' => '/icon-192x192.png',
            'data' => [
                'url' => '/tasks',
                'task_id' => $this->task->id,
                'type' => 'task_assigned',
            ],
        ];
    }
}
