<?php

require __DIR__ . '/../../config/bootstrap.php';

use Core\Database\Database;

echo "Recriando o banco de dados..." . PHP_EOL;
Database::migrate();

echo "Criando contas de teste..." . PHP_EOL;
require __DIR__ . '/auth_users.php';