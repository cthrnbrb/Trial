<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Password reset route for SPA - redirects to frontend
Route::get('/reset-password/{token}', function ($token) {
    return redirect('http://localhost:5173/reset-password?token=' . $token . '&email=' . request('email'));
})->name('password.reset');
