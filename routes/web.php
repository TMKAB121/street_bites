<?php

declare(strict_types=1);

use App\Actions\GenerateTruckMapImage;
use App\Actions\GeocodeSearch;
use App\Http\Middleware\RequireCookieConsent;
use App\Livewire\Auth\EmailEntry;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\LoginVerify;
use App\Livewire\Auth\SetPassword;
use App\Livewire\Auth\VerifyCode;
use App\Livewire\Profile\ProfilePage;
use App\Models\CookieConsent;
use App\Models\FoodTruck;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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

// ZIP/address → rough coordinates, for visitors who decline browser
// geolocation (<x-location-search> on the home page). Proxied through the
// server so the Nominatim usage policy (identifying User-Agent) is honoured;
// cached a day per normalized query and throttled since each miss is an
// external request. Lives under /api so validation errors render as JSON
// (bootstrap/app.php limits shouldRenderJsonWhen to api/*).
Route::get('/api/geocode', function (Request $request) {
    $request->validate([
        'q' => ['required', 'string', 'min:3', 'max:120'],
    ]);

    $query = mb_strtolower(trim($request->string('q')->toString()));

    $result = Cache::remember(
        'geocode:'.sha1($query),
        now()->addDay(),
        fn (): ?array => app(GeocodeSearch::class)($query),
    );

    return $result === null
        ? response()->json(['message' => 'No match for that location.'], 404)
        : response()->json($result);
})->middleware('throttle:15,1')->name('geocode');

// Records a cookie-consent decision from <x-cookie-consent>: documents it in
// cookie_consents (GDPR audit trail), then sets the consent cookie. Withdrawing
// consent while signed in also signs the visitor out (all-or-nothing — auth is
// cookie-backed), and the banner sends them home for a consistent guest view.
// Lives under /api so validation errors render as JSON (see /api/geocode).
Route::post('/api/cookie-consent', function (Request $request) {
    $data = $request->validate([
        'status' => ['required', 'string', 'in:accepted,declined'],
    ]);

    // Log first so a signed-in withdrawal is still attributed to the user.
    CookieConsent::log($data['status'], $request);

    $signedOut = $data['status'] === CookieConsent::STATUS_DECLINED && auth()->check();

    if ($signedOut) {
        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    return response()
        ->json(['status' => $data['status'], 'signedOut' => $signedOut])
        ->cookie(cookie(CookieConsent::COOKIE_NAME, $data['status'], CookieConsent::COOKIE_MINUTES));
})->middleware('throttle:30,1')->name('cookie-consent.store');

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

// Everything cookie-backed sits behind explicit consent (all-or-nothing: the
// app has no non-essential cookies, so declining simply forgoes accounts and
// favorites). Visitors without an 'accepted' consent cookie are sent home,
// where the banner reopens and explains.
Route::middleware(RequireCookieConsent::class)->group(function (): void {
    // Signed-in profile: favourited trucks + on-demand vendor truck management.
    Route::get('/profile', ProfilePage::class)->middleware('auth')->name('profile');

    // Email-verified sign-up flow: enter email → verify code → set password.
    Route::get('/auth/email', EmailEntry::class)->name('auth.email');
    Route::get('/auth/verify', VerifyCode::class)->name('auth.verify');
    Route::get('/auth/password', SetPassword::class)->name('auth.password');

    // Sign-in flow: password (primary factor) → emailed one-time code (second factor).
    Route::get('/auth/login', Login::class)->name('auth.login');
    Route::get('/auth/login/verify', LoginVerify::class)->name('auth.login.verify');
});
