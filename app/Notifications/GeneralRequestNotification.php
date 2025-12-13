<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GeneralRequestNotification extends Notification
{
    use Queueable;

    protected $generalRequest;
    protected $action; // 'created', 'approved', 'rejected', 'in_progress'

    public function __construct($generalRequest, $action = 'created')
    {
        $this->generalRequest = $generalRequest;
        $this->action = $action;
    }

    public function via(object $notifiable): array
    {
        return ['database', \App\Notifications\Channels\PushChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'request_id' => $this->generalRequest->id,
            'type' => $this->action === 'created' ? 'general_request_created' : 'general_request_' . $this->action,
            'status' => $this->generalRequest->status,
            'title' => $this->generalRequest->title,
            'team_member' => $this->generalRequest->team->full_name ?? 'N/A',
        ];
    }

    public function toPush(object $notifiable): array
    {
        $teamMember = $this->generalRequest->team->full_name ?? 'Team Member';
        $title = $this->generalRequest->title;
        
        if ($this->action === 'created') {
            return [
                'title' => 'New General Request',
                'body' => $teamMember . ' has submitted a request: ' . $title,
                'icon' => '/icon-192x192.png',
                'badge' => '/icon-192x192.png',
                'data' => [
                    'url' => '/general-requests',
                    'request_id' => $this->generalRequest->id,
                    'type' => 'general_request_created',
                ],
            ];
        } elseif ($this->action === 'approved') {
            return [
                'title' => 'Request Approved',
                'body' => 'Your request "' . $title . '" has been approved',
                'icon' => '/icon-192x192.png',
                'badge' => '/icon-192x192.png',
                'data' => [
                    'url' => '/general-requests',
                    'request_id' => $this->generalRequest->id,
                    'type' => 'general_request_approved',
                ],
            ];
        } elseif ($this->action === 'in_progress') {
            return [
                'title' => 'Request In Progress',
                'body' => 'Your request "' . $title . '" is now in progress',
                'icon' => '/icon-192x192.png',
                'badge' => '/icon-192x192.png',
                'data' => [
                    'url' => '/general-requests',
                    'request_id' => $this->generalRequest->id,
                    'type' => 'general_request_in_progress',
                ],
            ];
        } else {
            return [
                'title' => 'Request Rejected',
                'body' => 'Your request "' . $title . '" has been rejected',
                'icon' => '/icon-192x192.png',
                'badge' => '/icon-192x192.png',
                'data' => [
                    'url' => '/general-requests',
                    'request_id' => $this->generalRequest->id,
                    'type' => 'general_request_rejected',
                ],
            ];
        }
    }
}
