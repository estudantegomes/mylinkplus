<?php

namespace App\Controllers;

use App\Models\Tag;

class TagController extends OrganizationController
{
    protected string $model = Tag::class;
    protected string $resource = 'tags';
    protected string $label = 'Tags';
}
