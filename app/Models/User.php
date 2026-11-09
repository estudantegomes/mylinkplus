<?php

namespace App\Models;

use Lib\Validations;
use Core\Database\ActiveRecord\Model;
use Core\Database\ActiveRecord\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password_hash
 */
class User extends Model
{
    protected static string $table = 'users';
    protected static array $columns = ['name', 'email', 'password_hash', 'is_active', 'role'];

    protected ?string $password = null;
    protected ?string $password_confirmation = null;
    protected bool $is_active = true;
    protected string $role = 'user';

    public function validates(): void
    {
        Validations::notEmpty('name', $this);
        Validations::notEmpty('email', $this);

        if ($this->name !== null && $this->name !== '') {
            if (trim($this->name) === '' || preg_match('/\A.{1,255}\z/us', $this->name) !== 1) {
                $this->addError('name', 'Informe um nome com 1 a 255 caracteres.');
            }
        }

        if ($this->email !== null && $this->email !== '') {
            if (!filter_var($this->email, FILTER_VALIDATE_EMAIL) || strlen($this->email) > 255) {
                $this->addError('email', 'Informe um e-mail válido com até 255 caracteres.');
            }
        }

        Validations::uniqueness('email', $this);

        if ($this->newRecord()) {
            Validations::passwordConfirmation($this);

            if ($this->password === null || $this->password === '') {
                $this->addError('password', 'Informe uma senha.');
            } elseif (strlen($this->password) > 72 || str_contains($this->password, "\0")) {
                $this->addError('password', 'A senha deve ter até 72 bytes e não pode conter caracteres nulos.');
            }
        }
    }

    public function authenticate(string $password): bool
    {
        if ($this->password_hash == null) {
            return false;
        }

        return password_verify($password, $this->password_hash);
    }

    public static function findByEmail(string $email): User | null
    {
        return User::findBy(['email' => $email]);
    }

    public function __set(string $property, mixed $value): void
    {
        parent::__set($property, $value);

        if (
            $property === 'password' &&
            $this->newRecord() &&
            is_string($value) && $value !== '' &&
            strlen($value) <= 72 && !str_contains($value, "\0")
        ) {
            $this->password_hash = password_hash($value, PASSWORD_DEFAULT);
        }
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function profilePhotos(): HasMany
    {
        return $this->hasMany(ProfilePhoto::class, 'user_id');
    }

    public function currentProfilePhoto(): ?ProfilePhoto
    {
        return ProfilePhoto::findBy(['user_id' => $this->id, 'is_current' => true]);
    }

    public function profileInitials(): string
    {
        $label = trim($this->name ?? '') ?: explode('@', $this->email ?? 'U')[0];
        $words = preg_split('/\s+/u', $label, -1, PREG_SPLIT_NO_EMPTY) ?: ['U'];
        $initials = mb_substr($words[0], 0, 1);
        if (count($words) > 1) {
            $initials .= mb_substr($words[count($words) - 1], 0, 1);
        }

        return mb_strtoupper($initials, 'UTF-8');
    }
}
