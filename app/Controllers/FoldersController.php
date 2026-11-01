<?php

namespace App\Controllers;

use App\Models\Folder;
use Core\Http\Controllers\Controller;
use Core\Http\Request;
use Lib\Authentication\Auth;
use Lib\FlashMessage;

class FoldersController extends Controller
{
    public function create(Request $request): void
    {
        $this->validarCsrf($request);
        $user = Auth::user();

        if ($user === null) {
            $this->redirectTo('/login');
        }

        $name = trim((string) $request->getParam('name', ''));
        $parentIdRaw = $request->getParam('parent_id', null);
        $parentId = is_numeric($parentIdRaw) && (int) $parentIdRaw > 0 ? (int) $parentIdRaw : null;

        $folder = new Folder();
        $folder->__set('user_id', (int) $user->__get('id'));
        $folder->__set('name', $name);
        $folder->__set('parent_id', $parentId);

        if (!$folder->save()) {
            FlashMessage::danger('Erro ao criar pasta. O nome é obrigatório.');
        } else {
            FlashMessage::success('Pasta criada com sucesso!');
        }

        session_write_close();
        $this->redirectTo('/home');
    }

    public function update(Request $request): void
    {
        $this->validarCsrf($request);
        $user = Auth::user();

        if ($user === null) {
            $this->redirectTo('/login');
        }

        $id = (int) $request->getParam('id', 0);
        $name = trim((string) $request->getParam('name', ''));
        $parentIdRaw = $request->getParam('parent_id', null);
        $parentId = is_numeric($parentIdRaw) && (int) $parentIdRaw > 0 ? (int) $parentIdRaw : null;

        $folder = Folder::findByIdAndUser($id, (int) $user->__get('id'));

        if ($folder === null) {
            FlashMessage::danger('Pasta não encontrada.');
            $this->redirectTo('/home');
        }

        if ($parentId === $id) {
            $parentId = null;
        }

        $folder->__set('name', $name);
        $folder->__set('parent_id', $parentId);

        if (!$folder->save()) {
            FlashMessage::danger('Erro ao atualizar a pasta.');
        } else {
            FlashMessage::success('Pasta atualizada com sucesso!');
        }

        session_write_close();
        $this->redirectTo('/home');
    }

    public function destroy(Request $request): void
    {
        $this->validarCsrf($request);
        $user = Auth::user();

        if ($user === null) {
            $this->redirectTo('/login');
        }

        $id = (int) $request->getParam('id', 0);
        $folder = Folder::findByIdAndUser($id, (int) $user->__get('id'));

        if ($folder !== null) {
            $folder->destroy();
            FlashMessage::success('Pasta excluída com sucesso!');
        } else {
            FlashMessage::danger('Pasta não encontrada.');
        }

        session_write_close();
        $this->redirectTo('/home');
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
