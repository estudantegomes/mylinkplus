<?php

use App\Controllers\HomeController;
use App\Controllers\AdminController;
use Core\Router\Route;

//rotas publicas de login
Route::get('/', [HomeController::class, 'login'])->name('root');

Route::get('/login', [HomeController::class, 'login'])->name('login');

Route::post('/login', [HomeController::class, 'entrar'])->name('entrar');

//rotas restritas -> verifica a sessão antes de executar o controller
Route::middleware('auth')->group(function () {
    Route::get('/home', [
        HomeController::class,
        'index'
    ])->name('home');

    Route::post('/logout', [
        HomeController::class,
        'logout'
    ])->name('logout');

    //rotas administrativas -> verifica a sessão e a permissão de admin
    Route::middleware('admin')->group(function () {
        Route::get('/admin', [
            AdminController::class,
            'index'
        ])->name('admin');
    });
});