<?php

namespace App\Controllers;

use App\Models\User;
use Core\Http\Controllers\Controller;
use Core\Http\Request;
use Lib\Authentication\Auth;
use Lib\FlashMessage;
use App\Models\Folder;

class HomeController extends Controller
{
    public function login(): void
    {
        header('Cache-Control: no-store');

        if (Auth::check()) {
            $this->redirectTo('/home');
        }

        //token usado para conferir a origem dos formulários
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        $title = 'Login | MyLink+';

        $this->render('home/login/login', compact('title'));
    }

    public function entrar(Request $request): void
    {
        $this->validarCsrf($request);

        $email = $request->getParam('email', '');
        $password = $request->getParam('password', '');

        if (
            !is_string($email) ||
            !is_string($password) ||
            !filter_var(trim($email), FILTER_VALIDATE_EMAIL) ||
            $password === ''
        ) {
            FlashMessage::danger('Informe um e-mail e uma senha válidos.');

            $this->redirectTo('/login');
        }
        //autenticação
        $user = User::findByEmail(trim($email));

        if (
            $user === null ||
            !$user->authenticate($password) ||
            !$user->isActive()
        ) {
            FlashMessage::danger('E-mail ou senha inválidos.');

            $this->redirectTo('/login');
        }

        Auth::login($user);

        //renova token após autenticar
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        FlashMessage::success('Login realizado com sucesso!');

        $this->redirectTo('/home');
    }

    public function index(): void
    {
        $user = $this->currentUser();

        if ($user === null) {
            $this->redirectTo('/login');
            return;
        }

        $folders = Folder::where(['user_id' => $user->id]);
        $folderCount = count($folders);

        $rootFolders = array_slice(array_values(array_filter($folders, fn($folder) => $folder->parent_id === null)), 0, 5);

        $title = 'Home | MyLink+';
        $profilePhoto = $user->currentProfilePhoto();

        $this->render('home/index', compact('title', 'folderCount', 'rootFolders', 'user', 'profilePhoto'));
    }

    public function logout(Request $request): void
    {
        $this->validarCsrf($request);

        Auth::logout();

        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        FlashMessage::success('Você saiu da sua conta.');

        $this->redirectTo('/login');
    }

    private function validarCsrf(Request $request): void
    {
        $token = $request->getParam('csrf_token', '');
        $sessionToken = $_SESSION['csrf_token'] ?? '';

        if (
            !is_string($token) ||
            !is_string($sessionToken) ||
            $sessionToken === '' ||
            !hash_equals($sessionToken, $token)
        ) {
            http_response_code(403);
            exit('Formulário expirado ou inválido. Tente novamente.');
        }
    }
}
