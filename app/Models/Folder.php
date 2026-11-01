<?php

namespace App\Models;

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
{
    protected static string $table = 'folders';
    protected static array $columns = ['user_id', 'parent_id', 'name', 'color'];

    public function validates(): void
    {
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
    }
}
