<?php

declare(strict_types=1);

use App\Actions\BlockVendor;
use App\Actions\GenerateTruckMapImage;
use App\Actions\GeocodeSearch;
use App\Actions\RemoveTruck;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\RequireCookieConsent;
use App\Livewire\Admin\ModerationQueue;
use App\Livewire\Admin\NewsManager;
use App\Livewire\Auth\EmailEntry;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\LoginVerify;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Auth\ResetVerify;
use App\Livewire\Auth\SetPassword;
use App\Livewire\Auth\VerifyCode;
use App\Livewire\Profile\ProfilePage;
use App\Models\CookieConsent;
use App\Models\FoodTruck;
use App\Models\Post;
use App\Models\Tag;
use App\Models\TruckReport;
use App\Models\User;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', function (): Factory|View {
    $trucks = FoodTruck::query()
        ->where('is_published', true)
        ->with(['images', 'tags', 'todayHours'])
        // How many eaters favourited each truck — drives the Popular carousel.
        ->withCount('favoritedBy as favorites_count')
        // Flag each truck the signed-in visitor has favourited (is_favorited),
        // so the discovery cards can render their star filled. Guests skip the
        // subquery — their cards render no star at all.
        ->when(auth()->check(), fn ($query) => $query->withExists([
            'favoritedBy as is_favorited' => fn ($q) => $q->whereKey(auth()->id()),
        ]))
        ->orderBy('name')
        ->get()
        // Open-now trucks lead the list, alphabetical within each group. This is
        // the pre-geolocation order; once the visitor shares a location the
        // client-side truckDistanceSort re-sorts to closest-first, still
        // open-first (see resources/js/truck-map.js). isOpenNow() reads the
        // eager-loaded todayHours, so this adds no queries. sortByDesc is stable
        // (PHP 8), preserving the alphabetical order above within each group.
        ->sortByDesc->isOpenNow()
        ->values();

    // The Popular carousel: the ten most-favourited trucks, open-now first,
    // then by favourite count. Both sorts are stable, so cutting the top ten
    // by count *before* the open-first pass keeps count as the tie-breaker
    // within each open/closed group (and name below that, from $trucks above).
    $popular = $trucks
        ->sortByDesc('favorites_count')
        ->take(10)
        ->sortByDesc->isOpenNow()
        ->values();

    $tags = Tag::query()
        ->whereHas('foodTrucks', fn ($q) => $q->where('is_published', true))
        ->orderBy('name')
        ->get();

    return view('welcome', ['trucks' => $trucks, 'popular' => $popular, 'tags' => $tags]);
})->name('home');

// Search landing page — where the header search bar submits (Enter or the
// icon button). Lists matching published trucks as discovery cards; matching
// is by truck name, cuisine tag, or menu item name (FoodTruck::search()).
Route::get('/search', function (Request $request): Factory|View {
    $term = trim($request->string('q')->toString());

    $trucks = $term === ''
        ? collect()
        : FoodTruck::query()
            ->where('is_published', true)
            ->search($term)
            ->with(['images', 'tags', 'todayHours'])
            // Same is_favorited flag as home, so the cards' stars render.
            ->when(auth()->check(), fn ($query) => $query->withExists([
                'favoritedBy as is_favorited' => fn ($q) => $q->whereKey(auth()->id()),
            ]))
            ->orderBy('name')
            ->get()
            // Same pre-geolocation order as home: open-now trucks lead,
            // alphabetical within each group.
            ->sortByDesc->isOpenNow()
            ->values();

    return view('search', ['trucks' => $trucks, 'term' => $term]);
})->name('search');

// Typeahead suggestions for the header search bar: up to 8 published trucks
// matching by name, tag, or menu item, each with a link to its detail page
// and a short "why it matched" context line. Lives under /api so validation
// errors render as JSON (see /api/geocode).
Route::get('/api/search', function (Request $request) {
    $request->validate([
        'q' => ['required', 'string', 'min:2', 'max:100'],
    ]);

    $term = trim($request->string('q')->toString());
    $like = FoodTruck::likePattern($term);

    $trucks = FoodTruck::query()
        ->where('is_published', true)
        ->search($term)
        // Constrained eager loads: only the tags/menu items that themselves
        // match, so the context line below can explain non-name matches.
        ->with([
            'tags' => fn ($q) => $q->where('tags.name', 'like', $like),
            'menuItems' => fn ($q) => $q->where('name', 'like', $like),
        ])
        ->orderBy('name')
        ->limit(8)
        ->get();

    return response()->json($trucks->map(fn (FoodTruck $truck): array => [
        'id' => $truck->id,
        'name' => $truck->name,
        'url' => route('trucks.show', [$truck, $truck->slug]),
        // Why this truck matched, when the name alone doesn't show it.
        'context' => mb_stripos($truck->name, $term) !== false
            ? null
            : ($truck->tags->first()?->name ?? $truck->menuItems->first()?->name),
    ])->values());
})->middleware('throttle:60,1')->name('search.suggest');

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
        fn (): ?array => resolve(GeocodeSearch::class)($query),
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
// The id identifies the truck; the slug segment is descriptive (SEO/legibility)
// and must match the name-derived slug exactly — a wrong or stale slug 404s, so
// every URL that renders is the canonical one (<x-seo-meta> canonicalises to
// the current URL).
Route::get('/trucks/{truck}/{slug}', function (string $truck, string $slug): Factory|View {
    $truck = FoodTruck::query()
        ->where('is_published', true)
        ->with(['images', 'tags', 'menuItems', 'todayHours', 'socialLinks'])
        ->findOrFail($truck);

    abort_unless($slug === $truck->slug, 404);

    // Whether the signed-in visitor has favourited this truck (guests get no
    // star at all, so false is fine as their placeholder).
    $isFavorited = auth()->check()
        && $truck->favoritedBy()->whereKey(auth()->id())->exists();

    // Whether the signed-in visitor has already reported this truck, so the
    // report control renders in its "reported" state on a revisit. Guests can't
    // be recognised across requests, so they always start un-reported (the
    // endpoint dedupes their repeat click by hashed IP anyway).
    $isReported = auth()->check()
        && $truck->reports()
            ->where('status', TruckReport::STATUS_OPEN)
            ->where('user_id', auth()->id())
            ->exists();

    // Cached OSM static map of the pin's surroundings; null hides the section.
    $mapPath = resolve(GenerateTruckMapImage::class)($truck);
    $mapUrl = $mapPath !== null ? Storage::disk(config('filesystems.public_disk'))->url($mapPath) : null;

    return view('trucks.show', [
        'truck' => $truck,
        'mapUrl' => $mapUrl,
        'isFavorited' => $isFavorited,
        'isReported' => $isReported,
    ]);
})->whereNumber('truck')->name('trucks.show');

// Favourite/unfavourite toggle for the star buttons (<x-favorite-toggle> →
// resources/js/favorites.js). One endpoint, idempotent per pair: toggle()
// attaches or detaches the favorites pivot row and the response reports the
// resulting state, which the button settles on. Only published trucks can be
// favourited, matching their visibility everywhere else. Lives under /api so
// errors render as JSON (see /api/geocode).
Route::post('/api/favorites/{truck}', function (Request $request, string $truck) {
    $truck = FoodTruck::query()
        ->where('is_published', true)
        ->findOrFail($truck);

    /** @var User $user */
    $user = $request->user();

    $changes = $user->favorites()->toggle($truck);

    return response()->json(['favorited' => $changes['attached'] !== []]);
})->whereNumber('truck')->middleware(['auth', 'throttle:60,1'])->name('favorites.toggle');

// Public "report this truck" — any visitor (signed-in or not) can flag a truck
// as potentially offensive, surfacing it on the moderation queue's Reported tab.
// Deliberately NOT auth-gated (guests can report too) and surface-only: it never
// unpublishes the truck, so a single click can't be a takedown lever. Deduped per
// reporter (by user id when signed in, else by hashed IP) so counts can't be
// inflated, and throttled since it writes on an unauthenticated route. Published
// trucks only (404 otherwise), and lives under /api so errors render as JSON.
Route::post('/api/trucks/{truck}/report', function (Request $request, string $truck) {
    $truck = FoodTruck::query()
        ->where('is_published', true)
        ->findOrFail($truck);

    $userId = $request->user()?->id;
    $ip = $request->ip();
    $reporterHash = $ip === null ? null : hash('sha256', $ip);

    // One open report per reporter — a signed-in user is keyed by id, a guest by
    // hashed IP. A repeat click is a no-op that still reports success (idempotent).
    $alreadyReported = $truck->reports()
        ->where('status', TruckReport::STATUS_OPEN)
        ->when(
            $userId !== null,
            fn ($q) => $q->where('user_id', $userId),
            fn ($q) => $reporterHash !== null
                ? $q->whereNull('user_id')->where('reporter_hash', $reporterHash)
                : $q->whereRaw('1 = 0'),
        )
        ->exists();

    if (! $alreadyReported) {
        $truck->reports()->create([
            'user_id' => $userId,
            'reporter_hash' => $reporterHash,
            'status' => TruckReport::STATUS_OPEN,
        ]);
    }

    return response()->json(['reported' => true]);
})->whereNumber('truck')->middleware('throttle:10,1')->name('trucks.report');

// News & events landing page — a full-size search page for posts. Unlike
// /search (which prompts on an empty query), an empty q lists everything
// newest-first: the page doubles as the news feed. Matching is by title or
// body (Post::search()); query-filtered views are noindex in the view, the
// bare listing is crawlable.
Route::get('/news', function (Request $request): Factory|View {
    $term = trim($request->string('q')->toString());

    $posts = Post::query()
        ->where('is_published', true)
        ->when($term !== '', fn ($query) => $query->search($term))
        ->latest('published_at')
        ->latest('id')
        ->get();

    return view('news.index', ['posts' => $posts, 'term' => $term]);
})->name('news.index');

// Public story page — mirrors trucks.show: the id identifies the post, the
// slug segment is descriptive and must match the title-derived slug exactly
// (a wrong or stale slug 404s, so every URL that renders is the canonical
// one). Drafts stay invisible (404), matching the /news listing.
Route::get('/news/{post}/{slug}', function (string $post, string $slug): Factory|View {
    $post = Post::query()
        ->where('is_published', true)
        ->findOrFail($post);

    abort_unless($slug === $post->slug, 404);

    return view('news.show', ['post' => $post]);
})->whereNumber('post')->name('news.show');

// Public "About us" page — the mission, the developer, and where to follow the
// build. Static Blade view on the shared shell chrome, linked from the
// hamburger menu (and the desktop header nav).
Route::view('/about', 'about')->name('about');

// XML sitemap for search engines (advertised by public/robots.txt). Lists the
// public crawlable pages: home, about, the /news listing, and every published
// truck's and post's detail page (lastmod = the row's last update, so crawlers
// re-fetch renamed/edited entries). Generated per request — content published
// moments ago is already in the next fetch, with no file to rebuild or cache
// to bust; the queries are a few columns over published rows and crawlers
// fetch sitemaps rarely. Future public surfaces join by concat()ing their own
// URL entries.
Route::get('/sitemap.xml', function (): Response {
    $trucks = FoodTruck::query()
        ->where('is_published', true)
        ->orderBy('id')
        ->get(['id', 'name', 'updated_at']);

    $posts = Post::query()
        ->where('is_published', true)
        ->orderBy('id')
        ->get(['id', 'title', 'updated_at']);

    $urls = collect([
        ['loc' => route('home')],
        ['loc' => route('about')],
        ['loc' => route('news.index')],
    ])->concat($trucks->map(fn (FoodTruck $truck): array => [
        'loc' => route('trucks.show', [$truck, $truck->slug]),
        'lastmod' => $truck->updated_at?->toAtomString(),
    ]))->concat($posts->map(fn (Post $post): array => [
        'loc' => route('news.show', [$post, $post->slug]),
        'lastmod' => $post->updated_at?->toAtomString(),
    ]));

    // The XML declaration is prepended here, in plain PHP, rather than living in
    // the Blade view: a literal prolog in a template compiles to a cached PHP file
    // carrying open-tag bytes, which a server with short_open_tag=On mis-parses as
    // a PHP open tag (a production 500). Keeping it out of Blade sidesteps that.
    $body = view('sitemap', ['urls' => $urls])->render();

    return response('<?xml version="1.0" encoding="UTF-8"?>'."\n".$body, 200, [
        'Content-Type' => 'application/xml',
    ]);
})->name('sitemap');

// Living style guide — visual reference for the "Urban Vibrant" design tokens.
Route::view('/styleguide', 'styleguide');

// Everything cookie-backed sits behind explicit consent (all-or-nothing: the
// app has no non-essential cookies, so declining simply forgoes accounts and
// favorites). Visitors without an 'accepted' consent cookie are sent home,
// where the banner reopens and explains.
Route::middleware(RequireCookieConsent::class)->group(function (): void {
    // Signed-in profile: favourited trucks + on-demand vendor truck management.
    Route::get('/profile', ProfilePage::class)->middleware('auth')->name('profile');

    // Signed-in favorites page — the home page's discovery section
    // (<x-truck-discovery>: filters, ZIP fallback, map, sorted grid) scoped to
    // the trucks this user has starred.
    Route::get('/favorites', function (Request $request): Factory|View {
        /** @var User $user */
        $user = $request->user();

        $trucks = $user->favorites()
            ->where('is_published', true)
            ->with(['images', 'tags', 'todayHours'])
            // Everything here is favourited by definition, but the shared
            // discovery grid reads is_favorited — flag it the same way the
            // home page does so the stars render filled.
            ->withExists(['favoritedBy as is_favorited' => fn ($q) => $q->whereKey($user->id)])
            ->orderBy('name')
            ->get()
            // Same pre-geolocation order as home: open-now trucks lead,
            // alphabetical within each group (see the home route).
            ->sortByDesc->isOpenNow()
            ->values();

        // Only cuisines that appear among the favourites — pills for anything
        // else would filter down to an empty grid.
        $tags = Tag::query()
            ->whereHas('foodTrucks', fn ($q) => $q
                ->where('is_published', true)
                ->whereHas('favoritedBy', fn ($fq) => $fq->whereKey($user->id)))
            ->orderBy('name')
            ->get();

        return view('favorites', ['trucks' => $trucks, 'tags' => $tags]);
    })->middleware('auth')->name('favorites');

    // Email-verified sign-up flow: enter email → verify code → set password.
    Route::get('/auth/email', EmailEntry::class)->name('auth.email');
    Route::get('/auth/verify', VerifyCode::class)->name('auth.verify');
    Route::get('/auth/password', SetPassword::class)->name('auth.password');

    // Sign-in flow: password (primary factor) → emailed one-time code (second factor).
    Route::get('/auth/login', Login::class)->name('auth.login');
    Route::get('/auth/login/verify', LoginVerify::class)->name('auth.login.verify');

    // Password-reset flow: enter email → verify emailed code → set a new password.
    // Reuses the email-OTP engine; ownership of the inbox stands in for the
    // forgotten password (anti-enumeration at the request step — see ForgotPassword).
    Route::get('/auth/password/reset', ForgotPassword::class)->name('auth.password.request');
    Route::get('/auth/password/reset/verify', ResetVerify::class)->name('auth.password.verify');
    Route::get('/auth/password/reset/new', ResetPassword::class)->name('auth.password.reset');
});

// Sign out. A plain POST (CSRF-protected) rather than a Livewire action so the
// session teardown is a full request, not an AJAX round-trip. Deliberately
// outside RequireCookieConsent — logging out must always work, even if consent
// was somehow withdrawn — but behind `auth` so only a signed-in user can call it.
Route::post('/logout', function (Request $request) {
    auth()->logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('home');
})->middleware('auth')->name('logout');

// Delete the signed-in user's account. A plain CSRF POST (mirrors logout) so the
// deletion + session teardown happen in one full request, not an AJAX round-trip.
// The food_trucks.user_id FK is cascadeOnDelete, so deleting the user hard-deletes
// every truck they own and (cascading from each truck) its hours, images, menu
// items, tag pivots, social links, and favourite rows; cookie_consents.user_id is
// nulled so the GDPR audit trail outlives the account. DB cascade doesn't touch the
// public disk, so we clear each truck's stored images/maps first (withTrashed so
// admin-removed trucks' files go too). Irreversible — the UI gates it behind a
// confirm step.
Route::post('/account/delete', function (Request $request) {
    /** @var User $user */
    $user = $request->user();

    $disk = Storage::disk(config('filesystems.public_disk'));
    foreach ($user->foodTrucks()->withTrashed()->pluck('id') as $truckId) {
        $disk->deleteDirectory("truck-images/{$truckId}");
        $disk->deleteDirectory("truck-maps/{$truckId}");
    }

    auth()->logout();
    $user->delete();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('home');
})->middleware('auth')->name('account.destroy');

// Content-moderation admin surface. Behind auth + the config email allowlist
// (EnsureAdmin → User::isAdmin()) + cookie consent (auth is cookie-backed). The
// moderation queue lists every truck newest-first so an admin can review, remove
// (soft-delete), or block offending vendors. robots.txt disallows /admin.
Route::middleware(['auth', EnsureAdmin::class, RequireCookieConsent::class])
    ->prefix('admin')
    ->group(function (): void {
        Route::get('/trucks', ModerationQueue::class)->name('admin.trucks');
        // News & events authoring — the only way posts are created/edited.
        Route::get('/news', NewsManager::class)->name('admin.news');

        // Moderator actions on the public truck detail page (admin-only panel
        // there). Plain CSRF POSTs — the detail page is plain Blade, not
        // Livewire — routed through the same guards and the same shared actions
        // (RemoveTruck / BlockVendor) the moderation queue uses. Both 404 the
        // truck as a side effect (soft-deleted / unpublished), so each lands on
        // the moderation queue: remove on the Removed tab, block on review.
        Route::post('/trucks/{truck}/remove', function (FoodTruck $truck, RemoveTruck $removeTruck) {
            $removeTruck($truck);

            return redirect()->route('admin.trucks', ['filter' => 'removed']);
        })->whereNumber('truck')->name('admin.trucks.remove');

        Route::post('/trucks/{truck}/block', function (FoodTruck $truck, BlockVendor $blockVendor) {
            $blockVendor($truck->user);

            return redirect()->route('admin.trucks');
        })->whereNumber('truck')->name('admin.trucks.block');
    });
