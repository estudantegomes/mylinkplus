<?php

namespace App\Services;

use App\Models\ProfilePhoto;
use App\Models\User;
use Core\Constants\Constants;
use Core\Database\Database;
use Core\Exceptions\HTTPException;
use finfo;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

class ProfileAvatar
{
    public const MAX_BYTES = 2097152;
    public const MAX_PHOTOS = 10;
    public const MAX_PIXELS = 4000000;

    public function __construct(private ?string $directory = null)
    {
    }

    public function rootPath(): string
    {
        $testing = ($_ENV['APP_ENV'] ?? '') === 'testing';
        $configured = getenv($testing ? 'PROFILE_TEST_STORAGE_PATH' : 'PROFILE_STORAGE_PATH');
        $fallback = $testing ? 'storage/testing/profile' : 'storage/uploads/profile';

        return $this->directory ?? ($configured !== false && $configured !== ''
            ? $configured : (string) Constants::rootPath()->join($fallback));
    }

    /** @return array{tmp_name: string, original_name: string, width: int, height: int} */
    public function validate(mixed $file): array
    {
        if (!is_array($file) || !isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            throw new InvalidArgumentException('Selecione uma imagem PNG, JPEG ou WebP.');
        }
        if (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            throw new InvalidArgumentException('A imagem deve ter no máximo 2 MB.');
        }
        if ($file['error'] !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null)) {
            throw new InvalidArgumentException('O envio falhou. Selecione a imagem novamente.');
        }

        $path = $file['tmp_name'];
        $name = $file['name'] ?? null;
        if (!is_string($name) || preg_match('/\A[^\x00-\x1f\/\\\\]{1,255}\z/us', $name) !== 1) {
            throw new InvalidArgumentException('Nome de arquivo inválido.');
        }
        if (!is_file($path) || !is_readable($path)) {
            throw new InvalidArgumentException('O arquivo enviado não está disponível.');
        }
        $size = filesize($path);
        if ($size === false || $size < 1 || $size > self::MAX_BYTES) {
            throw new InvalidArgumentException('A imagem deve ter entre 1 byte e 2 MB.');
        }

        $allowed = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
        if (!isset($allowed[$extension]) || $mime !== $allowed[$extension]) {
            throw new InvalidArgumentException('Formato inválido. Envie somente PNG, JPEG ou WebP. SVG não é aceito.');
        }

        // Arquivos malformados podem emitir warnings; a resposta ao usuário é a validação abaixo.
        set_error_handler(static fn(): bool => true);
        try {
            $dimensions = getimagesize($path);
        } finally {
            restore_error_handler();
        }
        if ($dimensions === false || $dimensions['mime'] !== $mime) {
            throw new InvalidArgumentException('O arquivo não contém uma imagem válida.');
        }
        [$width, $height] = $dimensions;
        if ($width < 1 || $height < 1 || $width > 4096 || $height > 4096 || $width * $height > self::MAX_PIXELS) {
            throw new InvalidArgumentException('A imagem deve ter até 4096 pixels por lado e 4 milhões de pixels.');
        }

        return ['tmp_name' => $path, 'original_name' => $name, 'width' => $width, 'height' => $height];
    }

    /** @return array{original_name: string, storage_key: string, mime_type: string, size_bytes: int, width: int, height: int} */
    public function storeImage(mixed $file, int $ownerId): array
    {
        $info = $this->validate($file);
        if (!extension_loaded('gd') || !function_exists('imagewebp')) {
            throw new RuntimeException('Reconstrua a imagem Docker para habilitar GD com WebP.');
        }

        $key = bin2hex(random_bytes(32)) . '.webp';
        $targetPath = $this->path($ownerId, $key);
        $folder = dirname($targetPath);
        if (!is_dir($folder)) {
            if (!mkdir($folder, 02770, true) && !is_dir($folder)) {
                throw new RuntimeException('Não foi possível preparar o armazenamento das fotos.');
            }
            chmod($folder, 02770);
        }

        $bytes = file_get_contents($info['tmp_name']);
        if ($bytes === false) {
            throw new InvalidArgumentException('Não foi possível ler a imagem.');
        }
        set_error_handler(static fn(): bool => true);
        try {
            $source = imagecreatefromstring($bytes);
        } finally {
            restore_error_handler();
        }
        if ($source === false) {
            throw new InvalidArgumentException('A imagem está corrompida ou seu formato não é suportado.');
        }

        $side = min($info['width'], $info['height']);
        $outputSize = min(512, $side);
        $target = imagecreatetruecolor($outputSize, $outputSize);
        if ($target === false) {
            imagedestroy($source);
            throw new RuntimeException('Não foi possível processar a imagem.');
        }
        try {
            imagealphablending($target, false);
            imagesavealpha($target, true);
            imagefill($target, 0, 0, imagecolorallocatealpha($target, 0, 0, 0, 127));
            imagecopyresampled(
                $target,
                $source,
                0,
                0,
                (int) (($info['width'] - $side) / 2),
                (int) (($info['height'] - $side) / 2),
                $outputSize,
                $outputSize,
                $side,
                $side
            );
            if (!imagewebp($target, $targetPath, 85) || !is_file($targetPath) || filesize($targetPath) < 1) {
                throw new RuntimeException('Não foi possível gravar a foto processada.');
            }
            chmod($targetPath, 0660);
        } catch (Throwable $exception) {
            if (is_file($targetPath)) {
                unlink($targetPath);
            }
            throw $exception;
        } finally {
            imagedestroy($source);
            imagedestroy($target);
        }

        return [
            'original_name' => $info['original_name'], 'storage_key' => $key, 'mime_type' => 'image/webp',
            'size_bytes' => (int) filesize($targetPath), 'width' => $outputSize, 'height' => $outputSize,
        ];
    }

    public function upload(User $owner, mixed $file): ProfilePhoto
    {
        $info = $this->validate($file);
        if (!is_uploaded_file($info['tmp_name'])) {
            throw new InvalidArgumentException('O arquivo deve ser enviado pelo formulário de upload.');
        }

        $pdo = Database::getDatabaseConn();
        $stored = null;
        $pdo->beginTransaction();
        try {
            $this->lockOwner($pdo, $owner);
            $count = $pdo->prepare('SELECT COUNT(*) FROM profile_photos WHERE user_id = :owner');
            $count->execute(['owner' => $owner->id]);
            if ((int) $count->fetchColumn() >= self::MAX_PHOTOS) {
                throw new InvalidArgumentException('Você pode guardar até 10 fotos. Exclua uma antes de enviar outra.');
            }

            $stored = $this->storeImage($file, $owner->id);
            $photo = $owner->profilePhotos()->new($stored);
            if (!$photo instanceof ProfilePhoto || !$photo->isValid()) {
                throw new RuntimeException('Os metadados da imagem são inválidos.');
            }

            $clear = $pdo->prepare('UPDATE profile_photos SET is_current = FALSE WHERE user_id = :owner');
            $clear->execute(['owner' => $owner->id]);
            $insert = $pdo->prepare(
                'INSERT INTO profile_photos'
                . ' (user_id, original_name, storage_key, mime_type, size_bytes, width, height, is_current)'
                . ' VALUES (:owner, :original_name, :storage_key, :mime_type, :size_bytes, :width, :height, TRUE) RETURNING id'
            );
            $insert->execute(array_merge($stored, ['owner' => $owner->id]));
            $photo->id = (int) $insert->fetchColumn();
            $photo->is_current = true;
            $pdo->commit();

            return $photo;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($stored !== null && is_file($this->path($owner->id, $stored['storage_key']))) {
                unlink($this->path($owner->id, $stored['storage_key']));
            }
            throw $exception;
        }
    }

    public function select(User $owner, ProfilePhoto $photo): void
    {
        $pdo = Database::getDatabaseConn();
        $pdo->beginTransaction();
        try {
            $this->lockOwner($pdo, $owner);
            $this->lockPhoto($pdo, $owner, $photo);
            if (!is_file($this->filePath($photo))) {
                throw new HTTPException('Arquivo não encontrado.', 404);
            }
            $clear = $pdo->prepare('UPDATE profile_photos SET is_current = FALSE WHERE user_id = :owner');
            $clear->execute(['owner' => $owner->id]);
            $select = $pdo->prepare('UPDATE profile_photos SET is_current = TRUE WHERE id = :id AND user_id = :owner');
            $select->execute(['id' => $photo->id, 'owner' => $owner->id]);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function remove(User $owner, ProfilePhoto $photo): void
    {
        $pdo = Database::getDatabaseConn();
        $backup = null;
        $path = $this->filePath($photo);
        $pdo->beginTransaction();
        try {
            $this->lockOwner($pdo, $owner);
            $this->lockPhoto($pdo, $owner, $photo);
            if (is_file($path)) {
                $backup = file_get_contents($path);
                if ($backup === false || !unlink($path)) {
                    throw new RuntimeException('Não foi possível remover o arquivo. A foto foi mantida no banco.');
                }
            }
            $delete = $pdo->prepare('DELETE FROM profile_photos WHERE id = :id AND user_id = :owner');
            $delete->execute(['id' => $photo->id, 'owner' => $owner->id]);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (is_string($backup) && !is_file($path)) {
                if (file_put_contents($path, $backup, LOCK_EX) === false) {
                    throw new RuntimeException('Falha ao restaurar o arquivo após erro no banco.', 0, $exception);
                }
                chmod($path, 0660);
            }
            throw $exception;
        }
    }

    public function filePath(ProfilePhoto $photo): string
    {
        return $this->path($photo->user_id, $photo->storage_key);
    }

    private function path(int $ownerId, string $key): string
    {
        if ($ownerId < 1 || preg_match('/\A[a-f0-9]{64}\.webp\z/', $key) !== 1) {
            throw new InvalidArgumentException('Caminho de foto inválido.');
        }

        return rtrim($this->rootPath(), '/') . '/' . $ownerId . '/' . $key;
    }

    private function lockOwner(PDO $pdo, User $owner): void
    {
        $lock = $pdo->prepare('SELECT id FROM users WHERE id = :id FOR UPDATE');
        $lock->execute(['id' => $owner->id]);
        if ($lock->fetchColumn() === false) {
            throw new HTTPException('Usuário não encontrado.', 404);
        }
    }

    private function lockPhoto(PDO $pdo, User $owner, ProfilePhoto $photo): void
    {
        $lock = $pdo->prepare('SELECT id FROM profile_photos WHERE id = :id AND user_id = :owner FOR UPDATE');
        $lock->execute(['id' => $photo->id, 'owner' => $owner->id]);
        if ($photo->user_id !== $owner->id || $lock->fetchColumn() === false) {
            throw new HTTPException('Foto não encontrada.', 404);
        }
    }
}
