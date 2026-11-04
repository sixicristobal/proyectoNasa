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
        'username' => env('NASA_EARTHDATA_USERNAME') ?: env('EARTHDATA_USER'),
        'password' => env('NASA_EARTHDATA_PASSWORD') ?: env('EARTHDATA_PASS'),
        'token' => env('NASA_EARTHDATA_TOKEN') ?: env('EARTHDATA_TOKEN'),
    ],

    'cmr' => [
        'search_url' => env('CMR_SEARCH_URL', 'https://cmr.earthdata.nasa.gov/search/granules.json'),
    ],

    'appeears' => [
        'base_url' => env('APPEARS_BASE_URL', 'https://appeears.earthdatacloud.nasa.gov/api'),
        'smap_product' => env('APPEARS_SMAP_PRODUCT', 'SPL3SMP_E.006'),
        'smap_layer' => env('APPEARS_SMAP_LAYER', 'soil_moisture_am'),
        'smap_short_name' => env('APPEARS_SMAP_SHORT_NAME', 'SPL3SMP_E'),
        'smap_version' => env('APPEARS_SMAP_VERSION', '061'),
        'timeout' => (int) env('APPEARS_TIMEOUT', 1800),
        'poll' => (int) env('APPEARS_POLL', 20),
    ],

    'gpm' => [
        'short_name' => env('GPM_SHORT_NAME', 'GPM_3IMERGDF'),
        'version' => env('GPM_VERSION', '07'),
        'page_size' => (int) env('GPM_PAGE_SIZE', 40),
    ],

    'ollama' => [
        'host' => env('OLLAMA_HOST', 'http://100.107.189.14:11434'),
        'model' => env('OLLAMA_MODEL', 'llama3.2:1b'),
        // El servidor casero infiere en CPU (~2.4 tok/s con 1b): sin un timeout
        // largo y un num_predict acotado, la petición web siempre expira.
        'timeout' => (int) env('OLLAMA_TIMEOUT', 600),
        'num_predict' => (int) env('OLLAMA_NUM_PREDICT', 200),
    ],

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-3.8-flash'),
        'timeout' => (int) env('GEMINI_TIMEOUT', 120),
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
