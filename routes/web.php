<?php

use App\Http\Controllers\AppointmentController;
use App\Models\Service;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('home', ['services' => Service::orderBy('id')->get()]))->name('home');
Route::get('/book', [AppointmentController::class, 'create'])->name('booking.create');
Route::post('/book', [AppointmentController::class, 'store'])->middleware(['auth', 'auth.session', 'throttle:5,1'])->name('booking.store');

require __DIR__.'/accounts.php';
require __DIR__.'/payments.php';
