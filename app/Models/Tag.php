<?php

namespace App\Models;

use Core\Database\ActiveRecord\Model;
use Core\Database\ActiveRecord\BelongsTo;
use Core\Database\ActiveRecord\BelongsToMany;
use Lib\Validations;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string|null $color
 * @property string $created_at
 * @property User $user
 * @property array<Link> $links
 */

class Tag extends Model
{
    protected static string $table = 'tags';
    protected static array $columns = ['user_id', 'name', 'color'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Relação N:N inversa
    public function links(): BelongsToMany
    {
        return $this->BelongsToMany(
            Link::class,
            'link_tags', // tabela pivô
            'tag_id',    // chave estrangeira deste modelo na tabela pivô
            'link_id'    // chave estrangeira do modelo relacionado
        );
    }

    public function validates(): void
    {
        Validations::notEmpty('name', $this);
    }
}
