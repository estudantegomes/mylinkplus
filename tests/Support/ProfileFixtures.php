<?php

namespace Tests\Support;

use App\Models\ProfilePhoto;
use App\Models\User;
use App\Services\ProfileAvatar;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

class ProfileFixtures
{
    /** @return array{error: int, tmp_name: string, name: string, size: int} */
    public static function file(string $name = 'avatar.png'): array
    {
        $path = __DIR__ . '/Data/' . $name;

        return ['error' => UPLOAD_ERR_OK, 'tmp_name' => $path, 'name' => $name, 'size' => (int) filesize($path)];
    }

    public static function photo(User $owner, bool $current = false): ProfilePhoto
    {
        $attributes = (new ProfileAvatar())->storeImage(self::file(), $owner->id);
        $photo = new ProfilePhoto(array_merge($attributes, ['user_id' => $owner->id, 'is_current' => $current]));
        if (!$photo->save()) {
            throw new RuntimeException('Não foi possível criar a foto de teste.');
        }

        return $photo;
    }

    public static function clearFiles(): void
    {
        $root = (new ProfileAvatar())->rootPath();
        if (($_ENV['APP_ENV'] ?? '') !== 'testing' || !str_contains($root, 'testing')) {
            throw new RuntimeException('A limpeza exige um diretório exclusivo de testing.');
        }
        if (!is_dir($root)) {
            return;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $entry) {
            if ($entry->isDir() && !$entry->isLink()) {
                rmdir($entry->getPathname());
            } else {
                unlink($entry->getPathname());
            }
        }
    }
}
