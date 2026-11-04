<?php

use App\Http\Controllers\AuditController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\FinancialEntryController;
use App\Http\Controllers\MonthlyReportController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\StayController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;

Route::post('/locale', function (Request $request) {
    $data = $request->validate(['locale' => ['required', Rule::in(['my', 'en'])]]);
    $request->session()->put('locale', $data['locale']);

    return back();
})->middleware('throttle:20,1')->name('locale.update');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/login', [AuthController::class, 'authenticate'])->middleware('throttle:5,1')->name('login.submit');
});
Route::middleware(['auth', 'active', 'private'])->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/password', [AuthController::class, 'password'])->name('password.edit');
    Route::put('/password', [AuthController::class, 'updatePassword'])->name('password.update');
    Route::get('/rooms', [RoomController::class, 'index'])->name('rooms.index');
    Route::get('/rooms/all', [RoomController::class, 'index'])->name('rooms.all');
    Route::get('/rooms/{room}', [RoomController::class, 'show'])->withTrashed()->name('rooms.show');
    Route::patch('/rooms/{room}/status', [RoomController::class, 'status'])->name('rooms.status');
    Route::get('/stays', [StayController::class, 'index'])->name('stays.index');
    Route::get('/reservations', [ReservationController::class, 'index'])->name('reservations.index');
    Route::get('/reservations/create', [ReservationController::class, 'create'])->name('reservations.create');
    Route::post('/reservations', [ReservationController::class, 'store'])->name('reservations.store');
    Route::post('/reservations/{reservation}/check-in', [ReservationController::class, 'checkIn'])->name('reservations.check-in');
    Route::get('/reservations/{reservation}/edit', [ReservationController::class, 'edit'])->name('reservations.edit');
    Route::put('/reservations/{reservation}', [ReservationController::class, 'update'])->name('reservations.update');
    Route::post('/reservations/{reservation}/no-show', [ReservationController::class, 'noShow'])->name('reservations.no-show');
    Route::post('/reservations/{reservation}/cancel', [ReservationController::class, 'cancel'])->name('reservations.cancel');
    Route::get('/stays/create', [StayController::class, 'create'])->name('stays.create');
    Route::post('/stays', [StayController::class, 'store'])->name('stays.store');
    Route::get('/stays/{stay}', [StayController::class, 'show'])->withTrashed()->name('stays.show');
    Route::get('/stays/{stay}/edit', [StayController::class, 'edit'])->name('stays.edit');
    Route::get('/stays/{stay}/identity', [StayController::class, 'identityDocument'])->withTrashed()->name('stays.identity');
    Route::get('/stays/{stay}/identity/{document}', [StayController::class, 'identityDocument'])->withTrashed(['stay'])->name('stays.identity.show');
    Route::get('/stays/{stay}/payments/{payment}/receipt', [StayController::class, 'paymentReceipt'])->withTrashed()->name('stays.payments.receipt');
    Route::put('/stays/{stay}', [StayController::class, 'update'])->name('stays.update');
    Route::post('/stays/{stay}/charges', [StayController::class, 'charge'])->name('stays.charges');
    Route::post('/stays/{stay}/payments', [StayController::class, 'payment'])->withTrashed()->middleware('throttle:30,1')->name('stays.payments');
    Route::post('/stays/{stay}/checkout', [StayController::class, 'checkOut'])->name('stays.checkout');
    Route::get('/payments', PaymentController::class)->name('payments.index');
    Route::view('/reports', 'reports.index')->name('reports.index');
    Route::view('/settings', 'settings')->name('settings');
    Route::get('/reports/daily', ReportController::class)->name('reports.daily');
    Route::middleware('manager')->group(function () {
        Route::post('/stays/{stay}/payments/{payment}/reverse', [StayController::class, 'reversePayment'])->withTrashed()->name('stays.payments.reverse');
        Route::delete('/stays/{stay}/identity/{document}', [StayController::class, 'deleteIdentity'])->withTrashed(['stay'])->name('stays.identity.delete');
        Route::delete('/stays/{stay}/payments/{payment}/receipt', [StayController::class, 'deleteReceipt'])->withTrashed()->name('stays.payments.receipt.delete');
        Route::get('/reports/monthly', [MonthlyReportController::class, 'index'])->name('reports.monthly');
        Route::get('/reports/monthly/pdf', [MonthlyReportController::class, 'pdf'])->middleware('throttle:10,1')->name('reports.monthly.pdf');
        Route::get('/reports/monthly/statements', [MonthlyReportController::class, 'statements'])->name('reports.monthly.statements');
        Route::get('/reports/monthly/export', [MonthlyReportController::class, 'export'])->name('reports.monthly.export');
        Route::post('/financial-entries', [FinancialEntryController::class, 'store'])->name('financial-entries.store');
        Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
        Route::post('/expense-categories', [ExpenseCategoryController::class, 'store'])->name('expense-categories.store');
        Route::put('/expense-categories/{expenseCategory}', [ExpenseCategoryController::class, 'update'])->name('expense-categories.update');
        Route::post('/rooms', [RoomController::class, 'store'])->name('rooms.store');
        Route::post('/floors', [RoomController::class, 'storeFloor'])->name('floors.store');
        Route::put('/floors/{floor}', [RoomController::class, 'updateFloor'])->name('floors.update');
        Route::delete('/floors/{floor}', [RoomController::class, 'destroyFloor'])->name('floors.destroy');
        Route::post('/room-types', [RoomController::class, 'storeRoomType'])->name('room-types.store');
        Route::put('/room-types/{roomType}', [RoomController::class, 'updateRoomType'])->name('room-types.update');
        Route::delete('/room-types/{roomType}', [RoomController::class, 'destroyRoomType'])->name('room-types.destroy');
        Route::get('/rooms/{room}/edit', [RoomController::class, 'edit'])->name('rooms.edit');
        Route::put('/rooms/{room}', [RoomController::class, 'update'])->name('rooms.update');
        Route::delete('/rooms/{room}', [RoomController::class, 'destroy'])->name('rooms.destroy');
        Route::post('/rooms/{room}/restore', [RoomController::class, 'restore'])->withTrashed()->name('rooms.restore');
        Route::delete('/stays/{stay}', [StayController::class, 'destroy'])->name('stays.destroy');
        Route::post('/stays/{stay}/restore', [StayController::class, 'restore'])->withTrashed()->name('stays.restore');
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/{user}/photo', [UserController::class, 'photo'])->name('users.photo');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::get('/audit', AuditController::class)->name('audit.index');
    });
});
