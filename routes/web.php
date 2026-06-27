<?php

declare(strict_types=1);

use App\Livewire\Auth\EmailEntry;
use App\Livewire\Auth\SetPassword;
use App\Livewire\Auth\VerifyCode;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome'))->name('home');

// Living style guide — visual reference for the "Urban Vibrant" design tokens.
Route::view('/styleguide', 'styleguide');

// Email-verified sign-up flow: enter email → verify code → set password.
Route::get('/auth/email', EmailEntry::class)->name('auth.email');
Route::get('/auth/verify', VerifyCode::class)->name('auth.verify');
Route::get('/auth/password', SetPassword::class)->name('auth.password');
