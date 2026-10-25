<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require __DIR__ . '/../../vendor/autoload.php';

\Core\Env\EnvLoader::init();

$pdo = \Core\Database\Database::getDatabaseConn();

$accounts = [
    [
        'name' => 'Usuário de teste',
        'email' => 'usuario@mylink.test',
        'password' => 'Usuario@123',
        'role' => 'user',
    ],
    [
        'name' => 'Administrador de teste',
        'email' => 'admin@mylink.test',
        'password' => 'Admin@123',
        'role' => 'admin',
    ],
];

$sql = '
    INSERT INTO users (name, email, password_hash, is_active, role)
    VALUES (:name, :email, :password_hash, TRUE, :role)
    ON CONFLICT (email) DO NOTHING
';

$stmt = $pdo->prepare($sql);

foreach ($accounts as $account) {
    $stmt->execute([
        'name' => $account['name'],
        'email' => $account['email'],
        'password_hash' => password_hash(
            $account['password'],
            PASSWORD_DEFAULT
        ),
        'role' => $account['role'],
    ]);

    $status = $stmt->rowCount() > 0
        ? 'Conta criada'
        : 'Conta já existente; mantida sem alterações';

    echo $status . ': ' . $account['email'] . PHP_EOL;
}