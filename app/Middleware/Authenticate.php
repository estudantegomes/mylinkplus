<?php

namespace App\Middleware;

use Core\Http\Middleware\Middleware;
use Core\Http\Request;
use Lib\Authentication\Auth;
use Lib\FlashMessage;

class Authenticate implements Middleware
{
    public function handle(Request $request): void
    {
        header('Cache-Control: no-store');
        
        if (!Auth::check()) {
            FlashMessage::danger(
                'Faça login para acessar esta página.'
            );

            header('Location: /login');
            exit;
        }
    }
}