<?php

namespace App\Models;

use Core\Database\ActiveRecord\Model;

/**
 * @property ?int $user_id
 * @property ?string $name
 * @property ?string $color
 */
abstract class OrganizationItem extends Model
{
    public function validates(): void
    {
        if (trim($this->name ?? '') === '' || preg_match('/\A.{1,100}\z/us', $this->name ?? '') !== 1) {
            $this->addError('name', 'Informe um nome com 1 a 100 caracteres.');
        }

        if ($this->user_id === null || $this->user_id < 1) {
            $this->addError('user_id', 'Usuário inválido.');
        }

        if ($this->color !== null && !preg_match('/^#([0-9a-f]{6}|[0-9a-f]{8})$/i', $this->color)) {
            $this->addError('color', 'Use uma cor como #507CF3 ou deixe em branco.');
        }
    }
}
