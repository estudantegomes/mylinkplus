<?php

namespace App\Middleware;

use Core\Http\Middleware\Middleware;
use Core\Http\Request;
use Lib\Authentication\Auth;

class Administrator implements Middleware
{
    public function handle(Request $request): void
    {
        //exige sessão autenticada para prosseguir
        (new Authenticate())->handle($request);

        //permissão administrativa
        if (!Auth::isAdmin()) {
            http_response_code(403);

            header('Content-Type: text/html; charset=UTF-8');
            require__DIR__ . '/app/Views/errors/403.phtml';

            exit;
        }
    }
}
