<?php

namespace App\Models;

<<<<<<< HEAD
use Core\Database\ActiveRecord\BelongsTo;
use Core\Database\ActiveRecord\HasMany;
use Core\Database\ActiveRecord\Model;
use Lib\Validations;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $parent_id
 * @property string $name
 * @property string|null $color
 */
class Folder extends Model
=======
use Core\Database\Database;
use Throwable;

/** @property ?int $parent_id */
class Folder extends OrganizationItem
>>>>>>> backup/galeria-imagens-1xn
{
    protected static string $table = 'folders';
    protected static array $columns = ['user_id', 'parent_id', 'name', 'color'];

    public function validates(): void
    {
<<<<<<< HEAD
        Validations::notEmpty('name', $this);
        Validations::notEmpty('user_id', $this);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Folder::class, 'parent_id');
    }

    public function subFolders(): HasMany
    {
        return $this->hasMany(Folder::class, 'parent_id');
=======
        parent::validates();

        $ownerId = self::parseId($this->user_id);

        if ($ownerId === null) {
            $this->addError('user_id', 'Usuário inválido.');
            return;
        }

        $parentId = $this->parent_id;

        if ($parentId === null) {
            return;
        }

        $parentId = self::parseId($parentId);

        if ($parentId === null) {
            $this->addError('parent_id', 'Selecione uma pasta válida.');
            return;
        }

        $this->parent_id = $parentId;
        $visited = [];

        while ($parentId !== null) {
            if ($parentId === $this->id || isset($visited[$parentId])) {
                $this->addError('parent_id', 'Essa mudança criaria um ciclo entre as pastas.');
                return;
            }

            $parent = self::findBy(['id' => $parentId, 'user_id' => $ownerId]);

            if ($parent === null) {
                $this->addError('parent_id', 'A pasta pai não existe ou não está disponível para sua conta.');
                return;
            }

            $visited[$parentId] = true;
            $parentId = $parent->parent_id;
        }
    }

    public function destroy(): bool
    {
        $pdo = Database::getDatabaseConn();
        $pdo->beginTransaction();

        try {
            $lock = $pdo->prepare('SELECT id FROM folders WHERE id = :id AND user_id = :user_id FOR UPDATE');
            $lock->execute(['id' => $this->id, 'user_id' => $this->user_id]);

            $children = $pdo->prepare('SELECT id FROM folders WHERE parent_id = :id LIMIT 1');
            $children->execute(['id' => $this->id]);

            if ($lock->fetchColumn() === false || $children->fetchColumn() !== false) {
                $pdo->rollBack();
                $this->addError('base', 'Exclua ou mova as subpastas antes de excluir esta pasta.');
                return false;
            }

            $delete = $pdo->prepare('DELETE FROM folders WHERE id = :id AND user_id = :user_id');
            $delete->execute(['id' => $this->id, 'user_id' => $this->user_id]);

            $pdo->commit();
            return true;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
>>>>>>> backup/galeria-imagens-1xn
    }
}
