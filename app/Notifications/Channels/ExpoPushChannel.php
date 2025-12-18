<?php

namespace App\Notifications\Channels;

use App\Services\ExpoPushNotificationService;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class ExpoPushChannel
{
    protected $expoService;

    public function __construct(ExpoPushNotificationService $expoService)
    {
        $this->expoService = $expoService;
    }

    public function send($notifiable, Notification $notification)
    {
        if (!method_exists($notification, 'toExpoPush')) {
            return;
        }

        try {
            $message = $notification->toExpoPush($notifiable);
            
            $title = $message['title'] ?? 'Notification';
            $body = $message['body'] ?? '';
            $data = $message['data'] ?? [];

            // Send notification to user
            $results = $this->expoService->sendToUser(
                $notifiable->id,
                $title,
                $body,
                $data
            );

            // Log results for debugging
            if (!empty($results)) {
                foreach ($results as $result) {
                    if (isset($result['status']) && $result['status'] === 'error') {
                        Log::warning('Expo push notification error', [
                            'user_id' => $notifiable->id,
                            'error' => $result['message'] ?? 'Unknown error',
                        ]);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Expo push notification exception', [
                'user_id' => $notifiable->id,
                'message' => $e->getMessage(),
            ]);
        }
    }
}

