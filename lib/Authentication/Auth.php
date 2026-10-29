<?php

namespace Lib\Authentication;

use App\Models\User;

//cookie com o identificador da sessão
class Auth
{
    public static function login(User $user): void
    {
        //troca o identificador
        session_regenerate_id(true);

        //demo
        unset($_SESSION['mylink_demo']);

        //guarda somente o identificador
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

        return $user !== null && $user->is_active === true;
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
            && $user->is_active === true
            && $user->role === 'admin';
    }
}
