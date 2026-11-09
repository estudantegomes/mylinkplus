<?php

namespace App\Controllers;

use App\Models\ProfilePhoto;
use App\Models\User;
use App\Services\ProfileAvatar;
use Core\Exceptions\HTTPException;
use Core\Http\Controllers\Controller;
use Core\Http\Request;
use InvalidArgumentException;
use Lib\Csrf;
use Lib\FlashMessage;

class ProfileController extends Controller
{
    protected string $layout = 'profile';

    public function index(): void
    {
        $this->form();
    }

    public function upload(Request $request): void
    {
        Csrf::validate($request);
        $owner = $this->owner();
        try {
            (new ProfileAvatar())->upload($owner, $_FILES['photo'] ?? null);
        } catch (InvalidArgumentException $exception) {
            http_response_code(422);
            $this->form($exception->getMessage());
            return;
        }

        FlashMessage::success('Foto enviada e definida como sua foto de perfil.');
        $this->redirectTo('/profile');
    }

    public function select(Request $request): void
    {
        Csrf::validate($request);
        $owner = $this->owner();
        (new ProfileAvatar())->select($owner, $this->photo($request, $owner));
        FlashMessage::success('Foto de perfil atualizada.');
        $this->redirectTo('/profile');
    }

    public function destroy(Request $request): void
    {
        Csrf::validate($request);
        $owner = $this->owner();
        (new ProfileAvatar())->remove($owner, $this->photo($request, $owner));
        FlashMessage::success('Foto removida da galeria e do armazenamento.');
        $this->redirectTo('/profile');
    }

    public function image(Request $request): void
    {
        $photo = $this->photo($request, $this->owner());
        $path = (new ProfileAvatar())->filePath($photo);
        set_error_handler(static fn(): bool => true);
        try {
            $stream = fopen($path, 'rb');
        } finally {
            restore_error_handler();
        }
        if ($stream === false) {
            throw new HTTPException('Arquivo não encontrado.', 404);
        }
        try {
            $stat = fstat($stream);
            header('Content-Type: image/webp');
            header('Content-Disposition: inline; filename="avatar.webp"');
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: private, no-store');
            if ($stat !== false) {
                header('Content-Length: ' . $stat['size']);
            }
            fpassthru($stream);
        } finally {
            fclose($stream);
        }
    }

    private function owner(): User
    {
        $user = $this->currentUser();
        if ($user === null || !$user->isActive()) {
            throw new HTTPException('Autenticação necessária.', 401);
        }

        return $user;
    }

    private function photo(Request $request, User $owner): ProfilePhoto
    {
        $value = $request->getParam('id');
        $id = is_string($value) || is_int($value)
            ? filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]])
            : false;
        $photo = $id === false ? null : $owner->profilePhotos()->findById($id);
        if (!$photo instanceof ProfilePhoto) {
            throw new HTTPException('Foto não encontrada.', 404);
        }

        return $photo;
    }

    private function form(?string $error = null): void
    {
        header('Cache-Control: no-store');
        $user = $this->owner();
        /** @var ProfilePhoto[] $photos */
        $photos = $user->profilePhotos()->get();
        usort($photos, static fn($a, $b): int => ($b->is_current <=> $a->is_current) ?: ($b->id <=> $a->id));
        $profilePhoto = $user->currentProfilePhoto();
        $title = 'Meu perfil | MyLink+';
        $this->render('profile/index', compact('title', 'user', 'photos', 'profilePhoto', 'error'));
    }
}
