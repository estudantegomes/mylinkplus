<?php

namespace App\Controllers;

use App\Models\Folder;
use App\Models\User;
use Core\Http\Controllers\Controller;
use Core\Http\Request;
use Lib\Authentication\Auth;
use Lib\FlashMessage;

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

        $email = trim((string) $request->getParam('email', ''));
        $password = (string) $request->getParam('password', '');

        if (
            $email === '' ||
            $password === '' ||
            !filter_var($email, FILTER_VALIDATE_EMAIL)
        ) {
            FlashMessage::danger('Informe um e-mail e uma senha válidos.');
            $this->redirectTo('/login');
        }

        $user = User::findByEmail($email);

        if (
            $user === null ||
            !$user->authenticate($password) ||
            !$user->isActive()
        ) {
            FlashMessage::danger('E-mail ou senha inválidos.');
            $this->redirectTo('/login');
        }

        Auth::login($user);

        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        FlashMessage::success('Login realizado com sucesso!');

        // Garante a gravação dos dados em disco antes do redirecionamento HTTP
        session_write_close();
        $this->redirectTo('/home');
    }

    public function index(Request $request): void
    {
        $user = Auth::user();

        if ($user === null) {
            $this->redirectTo('/login');
        }

        $userId = (int) $user->__get('id');

        $rawFolderId = $request->getParam('folder_id', null);
        $currentFolderId = is_numeric($rawFolderId) && (int) $rawFolderId > 0 ? (int) $rawFolderId : null;

        $currentFolder = null;
        if ($currentFolderId !== null) {
            $currentFolder = Folder::findByIdAndUser($currentFolderId, $userId);
            if ($currentFolder === null) {
                $this->redirectTo('/home');
            }
        }

        $allFolders = Folder::allForUser($userId);
        $folders = Folder::allForUserAndParent($userId, $currentFolderId);
        $breadcrumbs = Folder::getBreadcrumb($currentFolderId, $userId);
        $title = 'Home | MyLink+';

        $this->render('home/index', compact(
            'title',
            'folders',
            'allFolders',
            'currentFolder',
            'breadcrumbs',
            'user'
        ));
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
