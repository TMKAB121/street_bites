<?php

declare(strict_types=1);

use App\Livewire\Auth\EmailEntry;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\LoginVerify;
use App\Livewire\Auth\SetPassword;
use App\Livewire\Auth\VerifyCode;
use App\Livewire\Profile\ProfilePage;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome'))->name('home');

// Living style guide — visual reference for the "Urban Vibrant" design tokens.
Route::view('/styleguide', 'styleguide');

// Signed-in profile: favourited trucks + on-demand vendor truck management.
Route::get('/profile', ProfilePage::class)->middleware('auth')->name('profile');

// Email-verified sign-up flow: enter email → verify code → set password.
Route::get('/auth/email', EmailEntry::class)->name('auth.email');
Route::get('/auth/verify', VerifyCode::class)->name('auth.verify');
Route::get('/auth/password', SetPassword::class)->name('auth.password');

// Sign-in flow: password (primary factor) → emailed one-time code (second factor).
Route::get('/auth/login', Login::class)->name('auth.login');
Route::get('/auth/login/verify', LoginVerify::class)->name('auth.login.verify');
