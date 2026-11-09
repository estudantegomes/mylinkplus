<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

try {
    require __DIR__ . '/auth_users.php';

    echo 'População concluída no banco: ' . $_ENV['DB_DATABASE'] . PHP_EOL;
} catch (\Throwable $exception) {
    fwrite(STDERR, 'Falha ao popular o banco: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

require __DIR__ . '/auth_users.php';
