<?php

declare(strict_types=1);

require_once __DIR__ . '/../_common.php';

api_handle(function (): void {
    api_require_method('GET');

    api_json([
        'ok' => true,
        'firebase' => [
            'apiKey' => lcm_config_value('FIREBASE_WEB_API_KEY', ''),
            'authDomain' => lcm_config_value('FIREBASE_AUTH_DOMAIN', 'lacablera.com'),
            'projectId' => lcm_config_value('FIREBASE_PROJECT_ID', ''),
            'storageBucket' => lcm_config_value('FIREBASE_STORAGE_BUCKET', ''),
            'messagingSenderId' => lcm_config_value('FIREBASE_MESSAGING_SENDER_ID', ''),
            'appId' => lcm_config_value('FIREBASE_APP_ID', ''),
            'measurementId' => lcm_config_value('FIREBASE_MEASUREMENT_ID', ''),
        ],
        'apiBaseUrl' => '/api/v1',
    ]);
});
