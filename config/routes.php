<?php

use App\Controllers\AdminController;
use App\Controllers\FolderController;
use App\Controllers\HomeController;
use App\Controllers\RegistrationController;
use App\Controllers\TagController;
use Core\Router\Route;

Route::get('/', [HomeController::class, 'login'])->name('root');
Route::get('/login', [HomeController::class, 'login'])->name('login');
Route::post('/login', [HomeController::class, 'entrar'])->name('entrar');

Route::get('/users/new', [RegistrationController::class, 'new'])->name('users.new');
Route::post('/users', [RegistrationController::class, 'create'])->name('users.create');

Route::middleware('auth')->group(function () {
    Route::get('/home', [HomeController::class, 'index'])->name('home');
    Route::post('/logout', [HomeController::class, 'logout'])->name('logout');

    foreach (['folders' => FolderController::class, 'tags' => TagController::class] as $resource => $controller) {
        Route::get('/' . $resource, [$controller, 'index'])->name($resource . '.index');
        Route::get('/' . $resource . '/new', [$controller, 'new'])->name($resource . '.new');
        Route::post('/' . $resource, [$controller, 'create'])->name($resource . '.create');
        Route::get('/' . $resource . '/{id}/edit', [$controller, 'edit'])->name($resource . '.edit');
        Route::get('/' . $resource . '/{id}', [$controller, 'show'])->name($resource . '.show');
        Route::put('/' . $resource . '/{id}', [$controller, 'update'])->name($resource . '.update');
        Route::delete('/' . $resource . '/{id}', [$controller, 'destroy'])->name($resource . '.destroy');
    }

    Route::middleware('admin')->group(function () {
        Route::get('/admin', [AdminController::class, 'index'])->name('admin');
    });
});
