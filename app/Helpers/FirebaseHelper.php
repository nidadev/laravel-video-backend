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
}
