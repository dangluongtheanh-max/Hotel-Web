<?php

declare(strict_types=1);

return [
    'name'     => 'Hotel Booking API',
    'env'      => $_ENV['APP_ENV'] ?? 'local',
    'debug'    => filter_var($_ENV['APP_DEBUG'] ?? 'false', FILTER_VALIDATE_BOOLEAN),
    'timezone' => 'Asia/Ho_Chi_Minh',
    'base_url' => $_ENV['APP_URL'] ?? 'http://localhost:8000',
];
