<?php

use App\Modules\Room\Infra\Http\Controllers\CreateRoomController;
use App\Modules\Room\Infra\Http\Controllers\DestroyRoomController;
use App\Modules\Room\Infra\Http\Controllers\EditRoomController;
use App\Modules\Room\Infra\Http\Controllers\IndexRoomController;
use App\Modules\Room\Infra\Http\Controllers\StoreRoomController;
use App\Modules\Room\Infra\Http\Controllers\UpdateRoomController;
use App\Modules\User\Infra\Http\Controllers\LoginController;
use App\Modules\User\Infra\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('guest')->group(function () {
    Route::get('/', [LoginController::class, 'create'])->name('home');
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');

    Route::get('/register', [UserController::class, 'create'])->name('register');
    Route::post('/register', [UserController::class, 'store'])->name('register.store');

    Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/reservations', function () {
        return Inertia::render('Reservation/Index');
    })->name('reservations.index');

    Route::get('/rooms', IndexRoomController::class)->name('rooms.index');
    Route::get('/rooms/create', CreateRoomController::class)->name('rooms.create');
    Route::post('/rooms', StoreRoomController::class)->name('rooms.store');
    Route::get('/rooms/{room}/edit', EditRoomController::class)->name('rooms.edit');
    Route::put('/rooms/{room}', UpdateRoomController::class)->name('rooms.update');
    Route::delete('/rooms/{room}', DestroyRoomController::class)->name('rooms.destroy');
});
