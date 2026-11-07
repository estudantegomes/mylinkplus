<?php

namespace Lib;

use Core\Exceptions\HTTPException;
use Core\Http\Request;

class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    public static function validate(Request $request): void
    {
        $expected = $_SESSION['csrf_token'] ?? '';
        $received = $request->getParam('csrf_token', '');

        if (!is_string($expected) || $expected === '' || !is_string($received) || !hash_equals($expected, $received)) {
            throw new HTTPException('Formulario expirado ou invalido.', 403);
        }
    }
}
