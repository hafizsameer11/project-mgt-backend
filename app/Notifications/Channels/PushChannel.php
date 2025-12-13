<?php

namespace App\Notifications\Channels;

use App\Models\PushSubscription;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

class PushChannel
{
    public function send($notifiable, Notification $notification)
    {
        if (!method_exists($notification, 'toPush')) {
            return;
        }

        // Call toPush method dynamically
        $reflection = new \ReflectionMethod($notification, 'toPush');
        $message = $reflection->invoke($notification, $notifiable);
        $subscriptions = PushSubscription::where('user_id', $notifiable->id)->get();

        if ($subscriptions->isEmpty()) {
            return;
        }

        // VAPID keys - these should be in your .env file
        $vapidPublicKey = env('VAPID_PUBLIC_KEY');
        $vapidPrivateKey = env('VAPID_PRIVATE_KEY');
        $vapidSubject = env('VAPID_SUBJECT', 'mailto:' . env('MAIL_FROM_ADDRESS'));

        if (!$vapidPublicKey || !$vapidPrivateKey) {
            Log::warning('VAPID keys not configured for push notifications');
            return;
        }

        $auth = [
            'VAPID' => [
                'subject' => $vapidSubject,
                'publicKey' => $vapidPublicKey,
                'privateKey' => $vapidPrivateKey,
            ],
        ];

        $webPush = new WebPush($auth);

        foreach ($subscriptions as $subscription) {
            try {
                $pushSubscription = Subscription::create([
                    'endpoint' => $subscription->endpoint,
                    'keys' => [
                        'p256dh' => $subscription->public_key,
                        'auth' => $subscription->auth_token,
                    ],
                    'contentEncoding' => $subscription->content_encoding ?? 'aesgcm',
                ]);

                $webPush->queueNotification(
                    $pushSubscription,
                    json_encode($message)
                );
            } catch (\Exception $e) {
                Log::error('Push notification error: ' . $e->getMessage());
                // Remove invalid subscription
                if (str_contains($e->getMessage(), '410') || str_contains($e->getMessage(), '404')) {
                    $subscription->delete();
                }
            }
        }

        foreach ($webPush->flush() as $report) {
            if (!$report->isSuccess()) {
                Log::error('Push notification failed: ' . $report->getReason());
                // Remove invalid subscription
                if (in_array($report->getStatusCode(), [410, 404])) {
                    $subscription = PushSubscription::where('endpoint', $report->getEndpoint())->first();
                    if ($subscription) {
                        $subscription->delete();
                    }
                }
            }
        }
    }
}

