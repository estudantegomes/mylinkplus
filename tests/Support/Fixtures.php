<?php

namespace Tests\Support;

use App\Models\User;
use RuntimeException;

class Fixtures
{
    public static function user(string $email = 'teste@example.com'): User
    {
        $user = new User([
            'name' => 'Usuário de teste',
            'email' => $email,
            'password' => 'Senha@12345',
            'password_confirmation' => 'Senha@12345',
        ]);

        if (!$user->save()) {
            throw new RuntimeException('Não foi possível criar o usuário de teste.');
        }

        return $user;
    }
}
