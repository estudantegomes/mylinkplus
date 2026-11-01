<?php

namespace App\Models;

use Core\Database\ActiveRecord\Model;
use Lib\Validations;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property int|null $parent_id
 * @property string $created_at
 */
class Folder extends Model
{
    protected static string $table = 'folders';
    protected static array $columns = ['user_id', 'name', 'parent_id'];

    public function validates(): void
    {
        Validations::notEmpty('name', $this);
    }

    /**
     * @return Folder[]
     */
    public static function allForUser(int $userId): array
    {
        /** @var Folder[] $all */
        $all = self::all();

        return array_values(array_filter(
            $all,
            fn(Folder $folder): bool => (int) $folder->__get('user_id') === $userId
        ));
    }

    /**
     * Retorna apenas as pastas filhas diretas de um determinado pai (null para a raiz).
     *
     * @return Folder[]
     */
    public static function allForUserAndParent(int $userId, ?int $parentId): array
    {
        $all = self::allForUser($userId);

        return array_values(array_filter(
            $all,
            function (Folder $folder) use ($parentId): bool {
                $pId = $folder->__get('parent_id');
                $pIdInt = $pId !== null ? (int) $pId : null;
                return $pIdInt === $parentId;
            }
        ));
    }

    /**
     * Retorna a lista de pastas ancestrais para o Breadcrumb.
     *
     * @return Folder[]
     */
    public static function getBreadcrumb(?int $currentFolderId, int $userId): array
    {
        $breadcrumb = [];
        $folderId = $currentFolderId;

        while ($folderId !== null && $folderId > 0) {
            $folder = self::findByIdAndUser($folderId, $userId);
            if ($folder === null) {
                break;
            }
            array_unshift($breadcrumb, $folder);
            $pId = $folder->__get('parent_id');
            $folderId = $pId !== null ? (int) $pId : null;
        }

        return $breadcrumb;
    }

    public static function findByIdAndUser(int $id, int $userId): ?Folder
    {
        /** @var static|null $folder */
        $folder = self::findBy(['id' => $id, 'user_id' => $userId]);
        return $folder;
    }
}
