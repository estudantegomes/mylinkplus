<?php

namespace Lib\Authentication;

use App\Models\User;

class Auth
{
    public static function login(User $user): void
    {
        //Renova o identificador da sessão após autenticar
        session_regenerate_id(true);

        // Remove o acesso demonstrativo antigo
        unset($_SESSION['mylink_demo']);

        // Guarda somente o identificador do usuário
        $_SESSION['user'] = [
            'id' => $user->id,
        ];
    }

    public static function user(): ?User
    {
        $id = $_SESSION['user']['id'] ?? null;

        if (!is_int($id) || $id < 1) {
            return null;
        }

        return User::findById($id);
    }

    public static function check(): bool
    {
        $user = self::user();

        return $user !== null && $user->isActive();
    }

    public static function logout(): void
    {
        unset($_SESSION['user'], $_SESSION['mylink_demo']);

        session_regenerate_id(true);
    }

    public static function isAdmin(): bool
    {
        $user = self::user();

        return $user !== null
            && $user->isActive()
            && $user->role === 'admin';
    }
}
