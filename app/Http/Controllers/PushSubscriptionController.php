<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'endpoint' => 'required|string',
            'keys' => 'required|array',
            'keys.p256dh' => 'required|string',
            'keys.auth' => 'required|string',
        ]);

        $user = $request->user();

        // Check if subscription already exists
        $subscription = PushSubscription::where('user_id', $user->id)
            ->where('endpoint', $request->endpoint)
            ->first();

        if ($subscription) {
            // Update existing subscription
            $subscription->update([
                'public_key' => $request->keys['p256dh'],
                'auth_token' => $request->keys['auth'],
                'content_encoding' => 'aesgcm',
            ]);
        } else {
            // Create new subscription
            PushSubscription::create([
                'user_id' => $user->id,
                'endpoint' => $request->endpoint,
                'public_key' => $request->keys['p256dh'],
                'auth_token' => $request->keys['auth'],
                'content_encoding' => 'aesgcm',
            ]);
        }

        return response()->json(['message' => 'Subscription saved successfully']);
    }

    public function destroy(Request $request)
    {
        $request->validate([
            'endpoint' => 'required|string',
        ]);

        $user = $request->user();

        PushSubscription::where('user_id', $user->id)
            ->where('endpoint', $request->endpoint)
            ->delete();

        return response()->json(['message' => 'Subscription removed successfully']);
    }

    public function getPublicKey()
    {
        $publicKey = env('VAPID_PUBLIC_KEY');
        if (!$publicKey) {
            return response()->json(['error' => 'VAPID public key not configured'], 500);
        }
        return response()->json(['publicKey' => $publicKey]);
    }
}
