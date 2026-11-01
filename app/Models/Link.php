<?php

namespace App\Models;

use Core\Database\ActiveRecord\Model;
use Core\Database\ActiveRecord\BelongsTo;
use Core\Database\ActiveRecord\BelongsToMany;
use Lib\Validations;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $folder_id
 * @property string $original_url
 * @property string $shortcode
 * @property string|null $title
 * @property string|null $qrcode_url
 * @property int $total_clicks
 * @property int $is_active
 * @property string $created_at
 * @property string $updated_at
 * @property User $user
 * @property Folder|null $folder
 * @property array<Tag> $tags
 */

class Link extends Model
{
    protected static string $table = 'links';
    protected static array $columns = ['user_id', 'folder_id', 'title', 'url', 'description'];

    protected int $user_id;
    protected ?int $folder_id = null;
    protected string $title = '';
    protected string $url = '';
    protected ?string $description = null;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class, 'folder_id');
    }

    // Relacao N:N com as Tags
    public function tags(): BelongsToMany
    {
        return $this->BelongsToMany(
            Tag::class,
            'link_tags', // tabela pivo
            'link_id',   // chave estrangeira deste modelo na tabela pivo
            'tag_id'     // chave estrangeira do modelo relacionado
        );
    }

    public function validates(): void
    {
        Validations::notEmpty('original_url', $this);
        Validations::notEmpty('shortcode', $this);
    }
}
