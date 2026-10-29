<?php

namespace App\Controllers;

use App\Models\User;
use Core\Http\Controllers\Controller;
use Core\Http\Request;
use Lib\Authentication\Auth;
use Lib\FlashMessage;

class AdminController extends Controller
{
    public function index(): void
    {
        $title = 'Administração | MyLink+';
        $user = $this->currentUser();

        $this->render('admin/index', compact('title', 'user'));
    }
}
