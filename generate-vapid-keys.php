<?php

/**
 * Generate VAPID keys for Web Push Notifications
 * 
 * Run this script to generate VAPID keys:
 * php generate-vapid-keys.php
 * 
 * Then add the keys to your .env file:
 * VAPID_PUBLIC_KEY=your_public_key_here
 * VAPID_PRIVATE_KEY=your_private_key_here
 * VAPID_SUBJECT=mailto:your-email@example.com
 */

require __DIR__ . '/vendor/autoload.php';

use Minishlink\WebPush\VAPID;

try {
    $keys = VAPID::createVapidKeys();
    
    echo "VAPID Keys Generated Successfully!\n\n";
    echo "Add these to your .env file:\n\n";
    echo "VAPID_PUBLIC_KEY=" . $keys['publicKey'] . "\n";
    echo "VAPID_PRIVATE_KEY=" . $keys['privateKey'] . "\n";
    echo "VAPID_SUBJECT=mailto:" . (getenv('MAIL_FROM_ADDRESS') ?: 'admin@example.com') . "\n\n";
    
} catch (Exception $e) {
    echo "Error generating VAPID keys: " . $e->getMessage() . "\n";
}

