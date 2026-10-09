<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'earthdata' => [
        'username' => env('NASA_EARTHDATA_USERNAME'),
        'password' => env('NASA_EARTHDATA_PASSWORD'),
        'token' => env('NASA_EARTHDATA_TOKEN'),
    ],

    'ollama' => [
        'host' => env('OLLAMA_HOST', 'http://100.107.189.14:11434'),
        'model' => env('OLLAMA_MODEL', 'llama3.2:3b'),
    ],

    'llm' => [
        'driver' => env('LLM_DRIVER', 'ollama'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
