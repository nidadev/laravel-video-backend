<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'twilio' => [
        'sid' => env('TWILIO_SID'),
        'token' => env('TWILIO_AUTH_TOKEN'),
        'from' => env('TWILIO_PHONE_NUMBER'),
    ],

    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        'public' => env('STRIPE_PUBLIC'),
    ],

    'mediaconvert' => [
        'role' => env('AWS_MEDIACONVERT_ROLE'),
        'endpoint' => env('AWS_MEDIACONVERT_ENDPOINT'),
    ],

    'cloudfront' => [
        'url' => env('CLOUDFRONT_URL', 'https://cdn.bitdrama.io'),
    ],

    'media' => [
        'raw_bucket' => env('AWS_RAW_VIDEO_BUCKET', env('AWS_BUCKET')),
        'processed_bucket' => env('AWS_PROCESSED_VIDEO_BUCKET', 'bitdrama-processed-videos-dev-643067044976'),
        'thumbnail_bucket' => env('AWS_THUMBNAILS_BUCKET', 'bitdrama-thumbnails-dev-643067044976'),
    ],

    'google_play' => [
        'package_name' => env('GOOGLE_PLAY_PACKAGE_NAME', 'com.hkasolution.bitdrama'),
        'service_account_json' => env('GOOGLE_PLAY_SERVICE_ACCOUNT_JSON'),
    ],

];
