<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome'));

// Living style guide — visual reference for the "Urban Vibrant" design tokens.
Route::view('/styleguide', 'styleguide');
