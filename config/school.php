<?php

return [
    'firebase_project' => env('FIREBASE_PROJECT_ID', 'sd-ceria-nusantara'),
    'firebase_database' => env('FIREBASE_DATABASE_ID', '(default)'),
    'firebase_credentials' => env('FIREBASE_CREDENTIALS', storage_path('app/private/firebase-service-account.json')),
    'firebase_credentials_json_base64' => env('FIREBASE_CREDENTIALS_JSON_BASE64'),
    'firebase_ca_bundle' => env('FIREBASE_CA_BUNDLE'),
    'landing_url' => env('LANDING_PAGE_URL', 'http://127.0.0.1:8000'),
    'landing_cache_prefix' => env('LANDING_CACHE_PREFIX', 'sd-ceria-nusantara-cache-'),
];
