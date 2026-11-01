<?php

namespace App\Models;

use Core\Database\ActiveRecord\Model;
use Lib\Validations;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $password_hash
 * @property bool $is_active
 * @property string $role
 */
class User extends Model
{
    protected static string $table = 'users';
    protected static array $columns = ['name', 'email', 'password_hash', 'is_active', 'role'];
    protected ?string $password = null;
    protected ?string $password_confirmation = null;

    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        if ($this->__get('is_active') === null) {
            $this->__set('is_active', true);
        }
    }

    public function validates(): void
    {
        Validations::notEmpty('name', $this);
        Validations::notEmpty('email', $this);
        Validations::uniqueness('email', $this);

        if ($this->newRecord()) {
            Validations::passwordConfirmation($this);
        }
    }

    public function authenticate(string $password): bool
    {
        $hash = $this->__get('password_hash');

        if (!is_string($hash) || $hash === '') {
            return false;
        }

        return password_verify($password, $hash);
    }

    public static function findByEmail(string $email): User | null
    {
        /** @var User|null $user */
        $user = User::findBy(['email' => $email]);
        return $user;
    }

    public function __set(string $property, mixed $value): void
    {
        parent::__set($property, $value);

        if (
            $property === 'password' &&
            $this->newRecord() &&
            $value !== null && $value !== ''
        ) {
            $this->__set('password_hash', password_hash($value, PASSWORD_DEFAULT));
        }
    }

    public function isActive(): bool
    {
        $active = $this->__get('is_active');

        if (is_bool($active)) {
            return $active;
        }

        return $active === 't' || $active === '1' || $active === 1 || $active === 'true';
    }
}
