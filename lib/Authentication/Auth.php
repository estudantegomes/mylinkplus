<?php

namespace Lib\Authentication;

use App\Models\User;

class Auth
{
    public static function login(User $user): void
    {
        session_regenerate_id(true);

        unset($_SESSION['mylink_demo']);

        $id = $user->__get('id') ?? $user->id;

        $_SESSION['user'] = [
            'id' => (int) $id,
        ];
    }

    public static function user(): ?User
    {
        $rawId = $_SESSION['user']['id'] ?? null;

        if ($rawId === null || !is_numeric($rawId)) {
            return null;
        }

        $id = (int) $rawId;

        if ($id < 1) {
            return null;
        }

        /** @var User|null $user */
        $user = User::findBy(['id' => $id]);
        return $user;
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
            && $user->__get('role') === 'admin';
    }
}
