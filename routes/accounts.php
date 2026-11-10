<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AdminAppointmentController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/login', [AuthController::class, 'authenticate'])->middleware('throttle:10,1')->name('login.store');
    Route::get('/register', [AuthController::class, 'register'])->name('register');
    Route::post('/register', [AuthController::class, 'store'])->middleware('throttle:5,1')->name('register.store');
    Route::get('/forgot-password', [AuthController::class, 'forgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->middleware('throttle:3,1')->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'resetPassword'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'updatePassword'])->middleware('throttle:5,1')->name('password.update');
});

Route::middleware(['auth', 'auth.session'])->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/account', [AccountController::class, 'index'])->name('account.index');
    Route::get('/account/appointments/{appointment}', [AccountController::class, 'show'])->name('appointments.show');
    Route::get('/admin/appointments', [AdminAppointmentController::class, 'index'])->name('admin.appointments.index');
});
