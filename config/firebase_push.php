<?php

return [
    'service_account_path' => env('FIREBASE_SERVICE_ACCOUNT_PATH'),
    'project_id' => env('FIREBASE_PROJECT_ID'),
    'server_key' => env('FIREBASE_SERVER_KEY'),
    'logging' => env('FIREBASE_PUSH_LOGGING', true),
];
