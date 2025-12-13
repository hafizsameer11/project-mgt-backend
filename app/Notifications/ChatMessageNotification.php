<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ChatMessageNotification extends Notification
{
    use Queueable;

    protected $message;
    protected $sender;

    public function __construct($message, $sender)
    {
        $this->message = $message;
        $this->sender = $sender;
    }

    public function via(object $notifiable): array
    {
        return ['database', \App\Notifications\Channels\PushChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'message_id' => $this->message->id,
            'sender_id' => $this->sender->id,
            'sender_name' => $this->sender->name,
            'message' => substr($this->message->message, 0, 100),
            'project_id' => $this->message->project_id,
            'type' => 'chat_message',
        ];
    }

    public function toPush(object $notifiable): array
    {
        $title = $this->message->project_id 
            ? 'New message in project chat'
            : 'New message from ' . $this->sender->name;
        
        return [
            'title' => $title,
            'body' => substr($this->message->message, 0, 100),
            'icon' => '/icon-192x192.png',
            'badge' => '/icon-192x192.png',
            'data' => [
                'url' => $this->message->project_id ? '/chat?project=' . $this->message->project_id : '/chat',
                'message_id' => $this->message->id,
                'type' => 'chat_message',
            ],
        ];
    }
}
