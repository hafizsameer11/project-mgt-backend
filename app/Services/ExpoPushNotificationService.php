<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExpoPushNotificationService
{
    private $apiUrl = 'https://exp.host/--/api/v2/push/send';

    /**
     * Send push notification to Expo push tokens
     *
     * @param array $tokens Array of Expo push tokens
     * @param string $title Notification title
     * @param string $body Notification body
     * @param array $data Additional data to send with notification
     * @return array Results of sending notifications
     */
    public function send(array $tokens, string $title, string $body, array $data = []): array
    {
        if (empty($tokens)) {
            return [];
        }

        // Expo API allows up to 100 tokens per request
        $chunks = array_chunk($tokens, 100);
        $results = [];

        foreach ($chunks as $chunk) {
            $messages = array_map(function ($token) use ($title, $body, $data) {
                return [
                    'to' => $token,
                    'sound' => 'default',
                    'title' => $title,
                    'body' => $body,
                    'data' => $data,
                    'badge' => 1,
                ];
            }, $chunk);

            try {
                $response = Http::withHeaders([
                    'Accept' => 'application/json',
                    'Accept-Encoding' => 'gzip, deflate',
                    'Content-Type' => 'application/json',
                ])->post($this->apiUrl, $messages);

                if ($response->successful()) {
                    $responseData = $response->json();
                    $results = array_merge($results, $responseData['data'] ?? []);
                } else {
                    Log::error('Expo push notification failed', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Expo push notification exception', [
                    'message' => $e->getMessage(),
                    'tokens' => $chunk,
                ]);
            }
        }

        return $results;
    }

    /**
     * Send notification to a single user
     *
     * @param int $userId User ID
     * @param string $title Notification title
     * @param string $body Notification body
     * @param array $data Additional data
     * @return array Results
     */
    public function sendToUser(int $userId, string $title, string $body, array $data = []): array
    {
        $tokens = \App\Models\PushSubscription::where('user_id', $userId)
            ->whereNotNull('expo_token')
            ->pluck('expo_token')
            ->toArray();

        if (empty($tokens)) {
            Log::info('No Expo push tokens found for user', ['user_id' => $userId]);
            return [];
        }

        return $this->send($tokens, $title, $body, $data);
    }

    /**
     * Send notification to multiple users
     *
     * @param array $userIds Array of user IDs
     * @param string $title Notification title
     * @param string $body Notification body
     * @param array $data Additional data
     * @return array Results
     */
    public function sendToUsers(array $userIds, string $title, string $body, array $data = []): array
    {
        $tokens = \App\Models\PushSubscription::whereIn('user_id', $userIds)
            ->whereNotNull('expo_token')
            ->pluck('expo_token')
            ->toArray();

        if (empty($tokens)) {
            Log::info('No Expo push tokens found for users', ['user_ids' => $userIds]);
            return [];
        }

        return $this->send($tokens, $title, $body, $data);
    }
}

