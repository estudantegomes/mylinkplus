<?php

namespace App\Models;

use Core\Database\ActiveRecord\BelongsTo;
use Core\Database\ActiveRecord\Model;

/**
 * @property int $user_id
 * @property string $original_name
 * @property string $storage_key
 * @property string $mime_type
 * @property int $size_bytes
 * @property int $width
 * @property int $height
 * @property bool $is_current
 */
class ProfilePhoto extends Model
{
    protected static string $table = 'profile_photos';
    protected static array $columns = [
        'user_id', 'original_name', 'storage_key', 'mime_type', 'size_bytes', 'width', 'height', 'is_current',
    ];

    protected bool $is_current = false;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function validates(): void
    {
        $owner = filter_var($this->user_id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($owner === false || User::findById($owner) === null) {
            $this->addError('user_id', 'Usuário inexistente.');
        }

        if (preg_match('/\A[^\x00-\x1f\/\\\\]{1,255}\z/us', $this->original_name ?? '') !== 1) {
            $this->addError('original_name', 'Nome de arquivo inválido.');
        }
        if (preg_match('/\A[a-f0-9]{64}\.webp\z/', $this->storage_key ?? '') !== 1) {
            $this->addError('storage_key', 'Identificador de arquivo inválido.');
        }
        if ($this->mime_type !== 'image/webp') {
            $this->addError('mime_type', 'A foto processada deve ser WebP.');
        }
        if (!$this->validSize($this->size_bytes)) {
            $this->addError('size_bytes', 'Tamanho de arquivo inválido.');
        }
        foreach (['width', 'height'] as $field) {
            if (!is_int($this->$field) || $this->$field < 1 || $this->$field > 512) {
                $this->addError($field, 'Dimensão de imagem inválida.');
            }
        }
    }

    private function validSize(mixed $value): bool
    {
        return is_int($value) && $value >= 1 && $value <= 2097152;
    }
}
