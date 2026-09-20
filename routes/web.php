<?php

use App\Modules\Reservation\Infra\Http\Controllers\CancelReservationController;
use App\Modules\Reservation\Infra\Http\Controllers\CreateReservationController;
use App\Modules\Reservation\Infra\Http\Controllers\EditReservationController;
use App\Modules\Reservation\Infra\Http\Controllers\IndexReservationController;
use App\Modules\Reservation\Infra\Http\Controllers\StoreReservationController;
use App\Modules\Reservation\Infra\Http\Controllers\UpdateReservationController;
use App\Modules\Room\Infra\Http\Controllers\CreateRoomController;
use App\Modules\Room\Infra\Http\Controllers\DestroyRoomController;
use App\Modules\Room\Infra\Http\Controllers\EditRoomController;
use App\Modules\Room\Infra\Http\Controllers\IndexRoomController;
use App\Modules\Room\Infra\Http\Controllers\StoreRoomController;
use App\Modules\Room\Infra\Http\Controllers\UpdateRoomController;
use App\Modules\User\Infra\Http\Controllers\AuthenticatedSessionController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/', [AuthenticatedSessionController::class, 'create'])->name('home');
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/reservations', IndexReservationController::class)->name('reservations.index');
    Route::get('/reservations/create', CreateReservationController::class)->name('reservations.create');
    Route::post('/reservations', StoreReservationController::class)->name('reservations.store');
    Route::get('/reservations/{reservation}/edit', EditReservationController::class)->name('reservations.edit');
    Route::put('/reservations/{reservation}', UpdateReservationController::class)->name('reservations.update');
    Route::patch('/reservations/{reservation}/cancel', CancelReservationController::class)->name('reservations.cancel');

    Route::get('/rooms', IndexRoomController::class)->name('rooms.index');
    Route::get('/rooms/create', CreateRoomController::class)->name('rooms.create');
    Route::post('/rooms', StoreRoomController::class)->name('rooms.store');
    Route::get('/rooms/{room}/edit', EditRoomController::class)->name('rooms.edit');
    Route::put('/rooms/{room}', UpdateRoomController::class)->name('rooms.update');
    Route::delete('/rooms/{room}', DestroyRoomController::class)->name('rooms.destroy');
});
