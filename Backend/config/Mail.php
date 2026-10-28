<?php

declare(strict_types=1);

return [
    'mailer' => $_ENV['MAIL_MAILER'] ?? 'smtp',
    'host' => $_ENV['MAIL_HOST'] ?? 'smtp.gmail.com',
    'port' => (int)($_ENV['MAIL_PORT'] ?? 587),
    'username' => $_ENV['MAIL_USERNAME'] ?? '',
    'password' => $_ENV['MAIL_PASSWORD'] ?? '',
    'encryption' => $_ENV['MAIL_ENCRYPTION'] ?? 'tls',
    'from_address' => $_ENV['MAIL_FROM_ADDRESS'] ?? ($_ENV['MAIL_USERNAME'] ?? ''),
    'from_name' => $_ENV['MAIL_FROM_NAME'] ?? 'Royal Lotus Hotel',
];
