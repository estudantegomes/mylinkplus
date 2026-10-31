<?php

require __DIR__ . '/../vendor/autoload.php';

\Core\Env\EnvLoader::init();

// Os testes criam e excluem bancos: exige o banco de testes.
if (($_ENV['DB_DATABASE'] ?? '') !== 'encurtador_test') {
    throw new \RuntimeException(
        'Os testes devem usar exclusivamente o banco encurtador_test.'
    );
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}