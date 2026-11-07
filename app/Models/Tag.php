<?php

namespace App\Models;

use Lib\Validations;

class Tag extends OrganizationItem
{
    protected static string $table = 'tags';
    protected static array $columns = ['user_id', 'name', 'color'];

    public function validates(): void
    {
        parent::validates();
        Validations::uniqueness(['user_id', 'name'], $this);
    }
}
