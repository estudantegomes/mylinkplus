<?php

namespace App\Models;

use Core\Database\ActiveRecord\Model;
use Lib\Validations;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password_hash
 * @property string $role
 * @property bool $is_active
 * @property string|null $password_confirmation
 * @property string $created_at
 */
class User extends Model
{
    protected static string $table = 'users';

    /**
     * @var array<int, string>
     */
    protected static array $columns = ['name', 'email', 'password_hash', 'role', 'is_active'];

    protected ?string $password = null;
    protected ?string $password_confirmation = null;

    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(array $attributes = [])
    {
        if (isset($attributes['password'])) {
            $pass = (string) $attributes['password'];
            $this->password = $pass;
            if (str_starts_with($pass, '$2y$') || str_starts_with($pass, '$2a$')) {
                $attributes['password_hash'] = $pass;
            }
            unset($attributes['password']);
        }

        if (isset($attributes['password_confirmation'])) {
            $this->password_confirmation = (string) $attributes['password_confirmation'];
            unset($attributes['password_confirmation']);
        }

        if (empty($attributes['role'])) {
            $attributes['role'] = 'user';
        }

        if (!isset($attributes['is_active'])) {
            $attributes['is_active'] = true;
        }

        parent::__construct($attributes);
    }

    public function __set(string $property, mixed $value): void
    {
        if ($property === 'password_confirmation') {
            $this->password_confirmation = is_string($value) && $value !== '' ? $value : null;
            return;
        }

        if ($property === 'password') {
            $this->password = is_string($value) && $value !== '' ? $value : null;
            return;
        }

        parent::__set($property, $value);
    }

    public function __get(string $property): mixed
    {
        if ($property === 'password') {
            return $this->password;
        }

        if ($property === 'password_confirmation') {
            return $this->password_confirmation;
        }

        return parent::__get($property);
    }

    public function validates(): void
    {
        Validations::notEmpty('name', $this);
        Validations::notEmpty('email', $this);

        if ($this->newRecord()) {
            Validations::notEmpty('password', $this);
            Validations::passwordConfirmation($this);
        } else {
            if ($this->password !== null && $this->password !== '') {
                Validations::passwordConfirmation($this);
            }
        }
    }

    public function save(): bool
    {
        if (!$this->isValid()) {
            return false;
        }

        if ($this->password !== null && $this->password !== '') {
            if (str_starts_with($this->password, '$2y$') || str_starts_with($this->password, '$2a$')) {
                parent::__set('password_hash', $this->password);
            } else {
                parent::__set('password_hash', password_hash($this->password, PASSWORD_BCRYPT));
            }
        }

        $saved = parent::save();
        if ($saved) {
            $this->password = null;
            $this->password_confirmation = null;
        }

        return $saved;
    }

    public static function findByEmail(string $email): ?User
    {
        /** @var static|null $user */
        $user = self::findBy(['email' => $email]);
        return $user;
    }

    public function authenticate(string $password): bool
    {
        $hash = (string) $this->__get('password_hash');
        if ($hash === '') {
            return false;
        }
        return password_verify($password, $hash);
    }

    public function isActive(): bool
    {
        return (bool) ($this->__get('is_active') ?? true);
    }

    public function isAdmin(): bool
    {
        return $this->__get('role') === 'admin';
    }
}
