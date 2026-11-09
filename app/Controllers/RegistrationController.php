<?php

namespace App\Controllers;

use App\Models\User;
use Core\Http\Controllers\Controller;
use Core\Http\Request;
use Lib\Authentication\Auth;
use Lib\Csrf;
use Lib\FlashMessage;
use PDOException;

class RegistrationController extends Controller
{
    public function new(): void
    {
        if (Auth::check()) {
            $this->redirectTo('/home');
            return;
        }

        $this->form(new User());
    }

    public function create(Request $request): void
    {
        Csrf::validate($request);

        if (Auth::check()) {
            $this->redirectTo('/home');
            return;
        }

        $params = [];

        foreach (['name', 'email', 'password', 'password_confirmation'] as $field) {
            $value = $request->getParam($field, '');
            $params[$field] = is_string($value) ? $value : '';
        }

        $params['name'] = trim($params['name']);
        $params['email'] = trim($params['email']);

        $user = new User($params);
        $user->isValid();

        if (preg_match('/\A.{8,}\z/us', $params['password']) !== 1) {
            $user->addError('password', 'Use pelo menos 8 caracteres na senha.');
        }

        try {
            if (!$user->hasErrors() && $user->save()) {
                FlashMessage::success('Cadastro concluído! Entre com seu e-mail e sua senha.');
                $this->redirectTo('/login');
                return;
            }
        } catch (PDOException $exception) {
            if ($exception->getCode() !== '23505') {
                throw $exception;
            }

            $user->addError('email', 'Este e-mail já está cadastrado.');
        }

        http_response_code(422);
        $this->form($user);
    }

    private function form(User $user): void
    {
        header('Cache-Control: no-store');

        $title = 'Criar conta | MyLink+';
        $this->render('users/new', compact('title', 'user'));
    }
}
