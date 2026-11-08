<?php

require __DIR__ . '/../../config/bootstrap.php';

use Core\Database\Database;

Database::migrate();

require __DIR__ . '/auth_users.php';
