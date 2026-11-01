<?php

namespace App\Models;

use Core\Database\ActiveRecord\Model;
use Lib\Validations;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $parent_id
 * @property string $name
 * @property string|null $color
 * @property string $created_at
 */
class Folder extends Model
{
    protected static string $table = 'folders';

    /**
     * @var array<int, string>
     */
    protected static array $columns = ['user_id', 'parent_id', 'name', 'color'];

    public function validates(): void
    {
        Validations::notEmpty('name', $this);

        $color = $this->__get('color');
        if ($color !== null && $color !== '') {
            if (!preg_match('/^#([0-9A-Fa-f]{6}|[0-9A-Fa-f]{8})$/', (string) $color)) {
                $this->addError('color', 'cor inválida');
            }
        }
    }

    public static function findByIdAndUser(int $id, int $userId): ?Folder
    {
        /** @var static|null $folder */
        $folder = self::findBy(['id' => $id, 'user_id' => $userId]);
        return $folder;
    }

    /**
     * @return array<int, Folder>
     */
    public static function allForUser(int $userId): array
    {
        /** @var array<int, Folder> $all */
        $all = self::all();
        return array_values(array_filter($all, fn(Folder $f) => (int) $f->__get('user_id') === $userId));
    }

    /**
     * @return array<int, Folder>
     */
    public static function allForUserAndParent(int $userId, ?int $parentId): array
    {
        /** @var array<int, Folder> $all */
        $all = self::all();
        return array_values(array_filter($all, function (Folder $f) use ($userId, $parentId) {
            $fUserId = (int) $f->__get('user_id');
            $fParentId = $f->__get('parent_id') !== null ? (int) $f->__get('parent_id') : null;
            return $fUserId === $userId && $fParentId === $parentId;
        }));
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    public static function getBreadcrumb(?int $folderId, int $userId): array
    {
        $breadcrumb = [];
        $currentId = $folderId;

        while ($currentId !== null) {
            $folder = self::findByIdAndUser($currentId, $userId);
            if ($folder === null) {
                break;
            }

            array_unshift($breadcrumb, [
                'id' => (int) $folder->__get('id'),
                'name' => (string) $folder->__get('name'),
            ]);

            $parentId = $folder->__get('parent_id');
            $currentId = $parentId !== null ? (int) $parentId : null;
        }

        return $breadcrumb;
    }

    public function user(): ?User
    {
        /** @var User|null $user */
        $user = User::findBy(['id' => (int) $this->__get('user_id')]);
        return $user;
    }

    public function parent(): ?Folder
    {
        $parentId = $this->__get('parent_id');
        if ($parentId === null) {
            return null;
        }
        /** @var static|null $folder */
        $folder = self::findBy(['id' => (int) $parentId]);
        return $folder;
    }

    /**
     * @return array<int, Folder>
     */
    public function children(): array
    {
        /** @var array<int, Folder> $all */
        $all = self::all();
        $id = (int) $this->__get('id');
        return array_values(array_filter($all, fn(Folder $f) => (int) $f->__get('parent_id') === $id));
    }
}
