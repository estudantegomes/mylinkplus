<?php

use App\Controllers\AdminController;
use App\Controllers\FoldersController;
use App\Controllers\HomeController;
use Core\Router\Route;

// Rotas públicas de login
Route::get('/', [HomeController::class, 'login'])->name('root');
Route::get('/login', [HomeController::class, 'login'])->name('login');
Route::post('/login', [HomeController::class, 'entrar'])->name('entrar');

// Rotas restritas -> verifica a sessão antes de executar o controller
Route::middleware('auth')->group(function () {
    Route::get('/home', [HomeController::class, 'index'])->name('home');
    Route::post('/logout', [HomeController::class, 'logout'])->name('logout');

    // CRUD de Pastas
    Route::get('/folders', [FoldersController::class, 'index'])->name('folders.index');
    Route::get('/folders/new', [FoldersController::class, 'new'])->name('folders.new');
    Route::post('/folders', [FoldersController::class, 'create'])->name('folders.create');
    Route::get('/folders/edit', [FoldersController::class, 'edit'])->name('folders.edit');
    Route::post('/folders/update', [FoldersController::class, 'update'])->name('folders.update');
    Route::post('/folders/destroy', [FoldersController::class, 'destroy'])->name('folders.destroy');

    // Rotas administrativas -> verifica a sessão e a permissão de admin
    Route::middleware('admin')->group(function () {
        Route::get('/admin', [AdminController::class, 'index'])->name('admin');
    });
});
