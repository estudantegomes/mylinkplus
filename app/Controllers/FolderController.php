<?php

namespace App\Controllers;

use App\Models\Folder;

class FolderController extends OrganizationController
{
    protected string $model = Folder::class;
    protected string $resource = 'folders';
    protected string $label = 'Pastas';
}
