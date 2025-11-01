<?php

namespace App\Helpers;

use Google\Client as GoogleClient;
use Illuminate\Support\Facades\Storage;

class FirebaseHelper
{
    public static function getAccessToken()
    {
        $credentialsPath = base_path(env('FIREBASE_CREDENTIALS'));

        if (!file_exists($credentialsPath)) {
            throw new \Exception("Firebase service account not found: " . $credentialsPath);
        }

        $client = new GoogleClient();
        $client->setAuthConfig($credentialsPath);
        $client->addScope('https://www.googleapis.com/auth/firebase.messaging');

        $accessToken = $client->fetchAccessTokenWithAssertion();
        return $accessToken['access_token'];
    }

    /**
     * Send push notification to a device via Firebase Cloud Messaging
     */
    public static function sendPushNotification($deviceToken, $title, $body)
    {
        try {
            $accessToken = self::getAccessToken();

            $response = Http::withToken($accessToken)
                ->post("https://fcm.googleapis.com/v1/projects/" . env('FIREBASE_PROJECT_ID') . "/messages:send", [
                    'message' => [
                        'token' => $deviceToken,
                        'notification' => [
                            'title' => $title,
                            'body' => $body,
                        ],
                    ],
                ]);

            if (!$response->successful()) {
                \Log::error('FCM Send Error: ' . $response->body());
            }
        } catch (\Exception $e) {
            \Log::error('Push notification failed: ' . $e->getMessage());
        }
    }
}
