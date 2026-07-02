<?php

declare(strict_types=1);

use App\Actions\GenerateTruckMapImage;
use App\Livewire\Auth\EmailEntry;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\LoginVerify;
use App\Livewire\Auth\SetPassword;
use App\Livewire\Auth\VerifyCode;
use App\Livewire\Profile\ProfilePage;
use App\Models\FoodTruck;
use App\Models\Tag;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    $trucks = FoodTruck::query()
        ->where('is_published', true)
        ->with(['images', 'tags'])
        ->orderBy('name')
        ->get();

    $tags = Tag::query()
        ->whereHas('foodTrucks', fn ($q) => $q->where('is_published', true))
        ->orderBy('name')
        ->get();

    return view('welcome', compact('trucks', 'tags'));
})->name('home');

// Public truck detail page — the destination of every truck card's FIND NOW
// CTA. Unpublished trucks stay invisible (404), matching home-page discovery.
Route::get('/trucks/{truck}', function (string $truck) {
    $truck = FoodTruck::query()
        ->where('is_published', true)
        ->with(['images', 'tags', 'menuItems', 'todayHours'])
        ->findOrFail($truck);

    // Cached OSM static map of the pin's surroundings; null hides the section.
    $mapPath = app(GenerateTruckMapImage::class)($truck);
    $mapUrl = $mapPath !== null ? Storage::disk('public')->url($mapPath) : null;

    return view('trucks.show', compact('truck', 'mapUrl'));
})->whereNumber('truck')->name('trucks.show');

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
