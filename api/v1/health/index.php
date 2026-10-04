<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

echo json_encode(
    [
        'ok' => true,
        'data' => [
            'service' => 'cablera-local',
            'api_version' => 'v1',
            'status' => 'healthy',
        ],
    ],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
