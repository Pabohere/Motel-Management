<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\StayController;
use App\Http\Controllers\UserController;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/login', [AuthController::class, 'authenticate'])->middleware('throttle:5,1')->name('login.submit');
});
Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/password', [AuthController::class, 'password'])->name('password.edit');
    Route::put('/password', [AuthController::class, 'updatePassword'])->name('password.update');
    Route::get('/rooms', [RoomController::class, 'index'])->name('rooms.index');
    Route::patch('/rooms/{room}/status', [RoomController::class, 'status'])->name('rooms.status');
    Route::get('/stays', [StayController::class, 'index'])->name('stays.index');
    Route::get('/stays/create', [StayController::class, 'create'])->name('stays.create');
    Route::post('/stays', [StayController::class, 'store'])->name('stays.store');
    Route::get('/stays/{stay}', [StayController::class, 'show'])->name('stays.show');
    Route::put('/stays/{stay}', [StayController::class, 'update'])->name('stays.update');
    Route::post('/stays/{stay}/charges', [StayController::class, 'charge'])->name('stays.charges');
    Route::post('/stays/{stay}/payments', [StayController::class, 'payment'])->middleware('throttle:30,1')->name('stays.payments');
    Route::post('/stays/{stay}/checkout', [StayController::class, 'checkOut'])->name('stays.checkout');
    Route::get('/reports/daily', ReportController::class)->name('reports.daily');
    Route::middleware('manager')->group(function () {
        Route::post('/rooms', [RoomController::class, 'store'])->name('rooms.store');
        Route::get('/rooms/{room}/edit', [RoomController::class, 'edit'])->name('rooms.edit');
        Route::put('/rooms/{room}', [RoomController::class, 'update'])->name('rooms.update');
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::get('/audit', fn () => view('audit.index', ['logs' => AuditLog::with('user')->latest('id')->paginate(30)]))->name('audit.index');
    });
});
