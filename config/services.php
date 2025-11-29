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

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'gravity_forms' => [
        'base_url' => env('GRAVITY_FORMS_BASE_URL', 'https://www.atlantafurnituremovers.com/gravityformsapi'),
        'public_key' => env('GRAVITY_FORMS_PUBLIC_KEY', '0b7fbd1824'),
        'private_key' => env('GRAVITY_FORMS_PRIVATE_KEY', '27842c3fdf765bd'),
        'form_id' => env('GRAVITY_FORMS_FORM_ID', '3'),
    ],

];
