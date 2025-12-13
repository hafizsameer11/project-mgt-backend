<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LeaveRequestNotification extends Notification
{
    use Queueable;

    protected $leaveRequest;
    protected $action; // 'created', 'approved', 'rejected'

    public function __construct($leaveRequest, $action = 'created')
    {
        $this->leaveRequest = $leaveRequest;
        $this->action = $action;
    }

    public function via(object $notifiable): array
    {
        return ['database', \App\Notifications\Channels\PushChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'request_id' => $this->leaveRequest->id,
            'type' => $this->action === 'created' ? 'leave_request_created' : 'leave_request_' . $this->action,
            'status' => $this->leaveRequest->status,
            'team_member' => $this->leaveRequest->team->full_name ?? 'N/A',
        ];
    }

    public function toPush(object $notifiable): array
    {
        $teamMember = $this->leaveRequest->team->full_name ?? 'Team Member';
        
        if ($this->action === 'created') {
            return [
                'title' => 'New Leave Request',
                'body' => $teamMember . ' has submitted a leave request (' . ($this->leaveRequest->days ?? 'N/A') . ' days)',
                'icon' => '/icon-192x192.png',
                'badge' => '/icon-192x192.png',
                'data' => [
                    'url' => '/leave-requests',
                    'request_id' => $this->leaveRequest->id,
                    'type' => 'leave_request_created',
                ],
            ];
        } elseif ($this->action === 'approved') {
            return [
                'title' => 'Leave Request Approved',
                'body' => 'Your leave request (' . ($this->leaveRequest->days ?? 'N/A') . ' days) has been approved',
                'icon' => '/icon-192x192.png',
                'badge' => '/icon-192x192.png',
                'data' => [
                    'url' => '/leave-requests',
                    'request_id' => $this->leaveRequest->id,
                    'type' => 'leave_request_approved',
                ],
            ];
        } else {
            return [
                'title' => 'Leave Request Rejected',
                'body' => 'Your leave request has been rejected' . ($this->leaveRequest->rejection_reason ? ': ' . substr($this->leaveRequest->rejection_reason, 0, 50) : ''),
                'icon' => '/icon-192x192.png',
                'badge' => '/icon-192x192.png',
                'data' => [
                    'url' => '/leave-requests',
                    'request_id' => $this->leaveRequest->id,
                    'type' => 'leave_request_rejected',
                ],
            ];
        }
    }
}
