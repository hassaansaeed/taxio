<?php

use Illuminate\Support\Facades\Route;
use Laravel\Socialite\Facades\Socialite;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');
});

Route::get('auth/{social}', function ($social) {

    if ($social == 'facebook') {
        return Socialite::driver('facebook')->redirect();
    }elseif ($social == 'google') {
        return Socialite::driver('google')->redirect();
    }else{
        return false;
    }

});

Route::get('auth/google/callback', [\App\Http\Controllers\Api\AuthController::class, 'googleLogin']);
