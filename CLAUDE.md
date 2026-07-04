# steet-bites

Laravel 13 · Livewire 4 · Reverb 1 · MariaDB 10.11 · Redis 7 · Vite/Node 20

Local dev runs entirely inside Lando (Docker). Do not assume local PHP, Composer, or Node. Prefix all runtime commands with `lando`.

## Nested memory (directory-scoped CLAUDE.md)

Directory-specific conventions live in nested `CLAUDE.md` files. Claude Code loads
each one **on demand** when it reads or edits a file in that subtree, so this root
file stays focused on cross-cutting context and the details load only when relevant.

| Path | Scope |
|---|---|
| `resources/css/CLAUDE.md` | Tailwind design tokens, the CSS partial map, BEM / contrast rules |
| `resources/views/CLAUDE.md` | Anonymous Blade components (table + conventions), view layout |
| `tests/CLAUDE.md` | Pest conventions (RefreshDatabase, lazy components, `Http::fake`, `travelTo`) |

**Maintenance rule:** put a new convention in the *most specific* file that always
loads for that work. Keep only cross-cutting/global context here in root — Lando,
the quality gates, Reverb, Vite, the database, and whole-feature narratives (auth,
profile/vendor, maps & geolocation). When a feature spans many directories, its
narrative stays in root; directory-local mechanics go in the nested file.

## Lando tooling

```bash
lando artisan <cmd>
lando composer <cmd>
lando npm <cmd>
lando logs -s reverb -f   # follow Reverb output (it auto-runs as the reverb service)
lando queue:work          # starts a Redis queue worker in the foreground
lando mariadb             # MariaDB shell
lando redis-cli           # Redis shell
lando pint                # Laravel Pint code style fixer
lando pest                # Pest test suite
```

## Code quality & linting

The project enforces a layered quality stack. Full detail lives in
`docs_and_archetecture/linting-and-code-quality.md` (in the sibling
`../docs_and_archetecture/` directory, outside the repo). Quick reference:

| Concern | Tool | Command |
|---|---|---|
| PHP style | Laravel Pint | `lando pint` / `lando pint --test` |
| PHP static analysis | Larastan (PHPStan, level 8) | `lando composer stan` |
| PHP refactoring | Rector | `lando composer rector:dry` / `lando composer rector` |
| PHP tests | Pest | `lando pest` / `lando composer test` |
| CSS lint | Stylelint | `lando npm run lint:css` |
| JS lint | ESLint | `lando npm run lint:js` |
| JS/JSON format | Prettier | `lando npm run format:check` |

A **pre-commit hook** at `.githooks/pre-commit` runs Pint, Larastan, ESLint,
Stylelint, and Prettier on every commit; a failure aborts the commit. It is
enabled via `git config core.hooksPath .githooks`, which `lando composer setup`
runs automatically. Rector is intentionally **not** in the hook — run it on
demand and review the diff. Bypass in an emergency with `git commit --no-verify`.

- PHP style is owned by **Pint** (not phpcs — they overlap).
- CSS formatting is owned by **Stylelint**; Prettier is scoped to JS/JSON only.
  Do not point Prettier at `*.css` or `*.blade.php`.
- PHPStan passes clean even at level 10; when real code can't pass, generate a
  baseline with `lando composer stan:baseline` and uncomment the include in
  `phpstan.neon`.

## Front-end / design system

Styling is **Tailwind CSS 4**, configured CSS-first (there is no
`tailwind.config.js`). The "Urban Vibrant" design system is encoded as Tailwind
`@theme` tokens in `resources/css/theme.css`, generating both CSS variables and
utility classes from one source of truth. Reusable UI is authored as **anonymous
Blade components** (a CSS partial + a `.blade.php` file) in
`resources/views/components/`.

**Brand assets** live in `public/`: `favicon.svg` (vector icon — the traced pin
mark), `favicon.ico` (16/32/48 fallback), `apple-touch-icon.png` (180², opaque),
and `public/images/` (`street-bites-logo.svg` — the master pin + wordmark used in
the header — plus transparent `street-bites-logo.png` / `street-bites-icon.png`
rasters). The SVGs are the masters (traced from the original art with potrace);
their fill is the logo's own brand red `#C72F2E` — close to but deliberately
**not** the `--color-accent-chili` token, so don't "fix" either to match the
other. Every full-page view links the favicon set in `<head>` (see
`resources/views/CLAUDE.md`).

Directory-local detail is in nested memory (loaded on demand — see *Nested memory*):

- **`resources/css/CLAUDE.md`** — the CSS partial map, design tokens, and the
  mustard/tangerine dark-text contrast rule.
- **`resources/views/CLAUDE.md`** — the Blade component table and its conventions
  (mobile-only positioning, inline-SVG icons, CSS scroll-snap).

The cross-cutting Alpine/Livewire rules and the route→view map stay here:

### JavaScript / Alpine

`resources/js/app.js` imports `echo.js` (Reverb) and `truck-map.js` (the Leaflet
home-page map). **Do not import or start Alpine here.** Livewire 4 bundles its own
Alpine and starts it automatically; running a second instance (the standalone
`alpinejs` package) triggers a "multiple instances of Alpine" conflict that
silently breaks every `wire:` directive. Livewire's bundled Alpine scans the whole
document, so plain `x-data`/`x-show` markup still works, and it's exposed on
`window.Alpine` for any custom directives. `[x-cloak]` is globally hidden in
`base.css` so collapsed UI never flashes on load. Prefer Alpine for pure-UI state;
reserve Livewire for server-backed interactivity.

`truck-map.js` registers four Alpine components on `alpine:init` (so they use
Livewire's bundled Alpine — never import Alpine): `truckMap(pins)` renders the
zoomable OSM/Leaflet map (opens at `MAP_ZOOM = 12`, ~5-mile radius; free zoom up to
OSM's tile max) with custom pin markers; `truckDistanceSort`
reorders a card list **open-first, then closest-first** (real DOM re-append) using each
card's `data-open` + `data-lat`/`data-lng`; `truckRadiusFilter` applies the radius cap
alone to lists that keep their server order (the Popular carousel, the search results
grid); `locationSearch(endpoint)` is the ZIP/address fallback form
(see *Maps & geolocation*). It also imports `leaflet/dist/leaflet.css`. The visitor's
position flows through two **window events** that decouple the pieces:
`user-located` `{ lat, lng }` (dispatched by the GPS success callback **and** by
`locationSearch` — `truckMap` listens and recenters/moves the "you are here" dot,
`truckDistanceSort` reorders) and `user-location-denied` (dispatched when GPS is
declined or missing — `locationSearch` reveals itself on it).

**100-mile radius cap:** once a location is known, every result surface hides trucks
beyond `MAX_RADIUS_MILES` (100, in `truck-map.js`) — `truckDistanceSort` and
`truckRadiusFilter` set the `hidden` attribute on out-of-range cards (Tailwind
preflight's `!important` display rule outranks the tag filter's `x-show`), and
`truckMap` drops out-of-range pins. Trucks without a pin stay visible (unknown ≠ far).
`truckMap` remembers each `user-located` position in `sessionStorage`
(`street-bites:user-location`) so map-less pages (`/search`) and revisits filter
immediately without their own GPS prompt — only the map writes the key, so the truck
form's pin fallback (same event, but the *truck's* location, on a map-less page) never
pollutes it. The cap is client-side only by necessity: the visitor's position is never
known server-side.

Two more Alpine components follow the same register-on-`alpine:init` pattern:
`favoriteToggle(endpoint, favorited, csrf)` (`resources/js/favorites.js`) powers the
`<x-favorite-toggle>` star — optimistic flip, then settles on the JSON answer from
`POST /api/favorites/{truck}`, rolling back on failure; `truckSearch(endpoint,
initial)` (`resources/js/search.js`) is the header search typeahead (see *Search*).
Both are imported by `app.js` alongside `truck-map.js`.

Because Livewire owns the JS, every full-page view must include `@livewireStyles`
in `<head>` and `@livewireScripts` before `</body>` — present in `welcome`,
`styleguide`, and both layouts (`layouts/app.blade.php`, `layouts/shell.blade.php`).

### Pages

- `/` → `welcome.blade.php` — the **assembled mobile shell**: `<x-mobile-header>`,
  the **Popular carousel** (`<x-card-carousel>`), the shared `<x-truck-discovery>`
  section (filters + ZIP fallback + Leaflet map + results grid), and `<x-mobile-nav>`.
  Content uses `pt-32 pb-24 md:pt-8 md:pb-8` to clear the fixed bars on mobile.
  The route closure queries published `FoodTruck`s (with images, tags + `todayHours`
  eager-loaded, `withCount('favoritedBy as favorites_count')`, and — signed-in only —
  `withExists` as `is_favorited` so the cards' stars render) and all `Tag`s that have
  at least one published truck. The truck collection is `sortByDesc->isOpenNow()`
  before rendering so **currently-open trucks lead** (alphabetical within each group)
  — the pre-geolocation order; `truckDistanceSort` re-sorts the grid open-first, then
  closest-first once GPS is granted. `$popular` is the **ten most-favourited trucks**,
  open-now first then by favourite count (stable sorts keep count as the tie-breaker)
  — popularity drives the carousel, so it keeps its server order (no distance sort),
  but `truckRadiusFilter` hides its cards beyond the 100-mile cap and collapses the
  whole section when none remain in range (see *100-mile radius cap*).
  Client-side tag filtering is Alpine-driven via the `tag-filter` window event.
- `/favorites` → `favorites.blade.php` (`favorites`, `auth` + consent middleware) —
  the home page's discovery section (`<x-truck-discovery>`) scoped to the trucks the
  user has starred, with the tag pills limited to cuisines that appear among them.
- `/search` → `search.blade.php` (`search`) — the **search landing page** the header
  search bar submits to (see *Search*): a plain grid of matching discovery cards,
  open-now first. Empty query prompts; no map/filters — refining happens by searching
  again from the still-visible, pre-filled header. `truckRadiusFilter` hides matches
  beyond the 100-mile cap using the session-remembered location (no map here to
  prompt for GPS), with an "n matches are more than 100 miles away" note.
- `/trucks/{truck}` → `trucks/show.blade.php` (`trucks.show`, `whereNumber`) — the
  **public truck detail page** discovery cards link to. Plain Blade view (no Livewire):
  cached OSM static map with a centred pin, cuisine tags, today's hours
  ("Open today …" / "Open now — since …" / unposted), location, and menu. The route
  eager-loads `images`, `tags`, `menuItems`, `todayHours` and passes `$mapUrl` from
  `GenerateTruckMapImage`. Unpublished/missing trucks 404. See *Maps & geolocation*.
- `/about` → `about.blade.php` (`about`) — public **"About us"** page: the mission
  (founding question as a pull-quote), the developer intro, and follow-along link
  cards (YouTube / GitHub / LinkedIn). Static `Route::view` on the shell layout,
  linked from the hamburger menu (`active="about"`); bespoke visuals in
  `resources/css/components/about.css`.
- `/profile` → `App\Livewire\Profile\ProfilePage` (`auth` middleware) — the
  signed-in profile (see *Profile & vendor management* below). Uses the
  `layouts/shell.blade.php` layout, which factors the welcome shell's chrome
  (fixed header + bottom nav + `<x-toast>`) into a reusable layout for app pages.
- `/styleguide` → `styleguide.blade.php` — living style guide demoing every token
  and component in isolation (route in `routes/web.php`).

## Authentication

Two email-verified flows live under `app/Livewire/Auth/` (full-page Livewire
components; views in `resources/views/livewire/auth/`). State passes between steps
via the session; the user is logged in only at the final step.

| Flow | Steps (route → component) | Notes |
|---|---|---|
| Sign-up | `auth.email` → `auth.verify` → `auth.password` (`EmailEntry` → `VerifyCode` → `SetPassword`) | Verify email via 6-digit code, then set a password and create the account |
| Sign-in | `auth.login` → `auth.login.verify` (`Login` → `LoginVerify`) | Password (primary factor) → emailed 6-digit code (second factor) → home |

- **One-time codes:** `App\Models\EmailVerification` stores a **hashed** 6-digit
  code per email (10-min TTL, 5-attempt cap, burned on success/exhaustion). Mailed
  via `EmailVerificationCode` (sign-up — includes an auto-verifying signed magic
  link to `auth.verify`) and `LoginCode` (sign-in — code only, **no** magic link,
  so it can't drop the user into the sign-up flow).
- **Email-as-2FA is deliberate** — the second factor stays email OTP (not
  TOTP/SMS) to limit PII and complexity. Don't swap it without a product decision.
- **Stepped auth + anti-enumeration:** `Login` validates the password first, then
  hands off; a single generic error covers both unknown email and wrong password,
  and the code step gives a generic "invalid or expired" error. Every auth step
  is rate-limited through the shared `ThrottlesAttempts` trait
  (`app/Livewire/Auth/Concerns/`) — one policy (5 attempts / rolling minute) and
  one generic "slow down" error for all flows. Pending sign-in is tracked by
  `session('auth.login.pending')` = the user id only — never the password.

### Password & session security (NIST SP 800-63B / OWASP)

- **Hashing: Argon2id** (memory-hard) — `config/hashing.php`, `HASH_DRIVER=argon2id`.
  `rehash_on_login` upgrades legacy bcrypt hashes to Argon2id on the next sign-in
  (done in `Login` while the plaintext is in hand); the salt is embedded per
  password. **Tests override `HASH_DRIVER=bcrypt` (rounds=4) in `phpunit.xml` for
  speed** — don't assert the Argon2id hash format under the default test driver.
- **Password policy** is centralised in `AppServiceProvider::boot()` via
  `Password::defaults()`: **min 12 chars, no forced character classes** (length
  over complexity) plus a **Have I Been Pwned breach check** (`uncompromised()`)
  that is skipped only under the test suite (`runningUnitTests()`). `SetPassword`
  also caps length at `max:128` (hashing-DoS guard).
- **Session cookies** are hardened in `.env` / `.env.example`:
  `SESSION_SECURE_COOKIE`, `SESSION_HTTP_ONLY`, `SESSION_SAME_SITE=lax`, and
  `SESSION_ENCRYPT` are all on, and the session id is regenerated on successful
  sign-in/sign-up. `APP_URL` is HTTPS locally (lndo.site), so the Secure flag
  doesn't break local auth.

- **Guest redirect:** the sign-in route is named `auth.login` (there is no `login`
  route), so `bootstrap/app.php` sets `$middleware->redirectGuestsTo(fn () =>
  route('auth.login'))`. Without it the `auth` middleware would error resolving the
  default `login` route.

## Cookie consent (GDPR)

**All-or-nothing by design** — the app sets no analytics/marketing cookies, so
there are no categories to pick: the visitor either accepts essential cookies
(session-backed sign-in + favorites) or browses anonymously. This is a product
decision; don't add a category picker without one.

- `<x-cookie-consent>` (banner + a persistent round "cookie preferences" button
  that reopens it — withdrawal must stay as easy as consent) is stacked once per
  full page: both layouts, `welcome`, `styleguide`. The choice lives in the
  **encrypted** `cookie_consent` cookie (`accepted`/`declined`, ~6 months so
  consent is re-prompted periodically) and is read **server-side** in the
  component, so there's no banner flash for returning visitors.
- **Every decision is documented** (GDPR audit duty): `POST /api/cookie-consent`
  (`cookie-consent.store`, throttled) appends a `cookie_consents` row —
  status, policy version (`CookieConsent::POLICY_VERSION`, bump on copy
  changes), **hashed** IP (data minimisation), user agent, and the user id when
  signed in. Rows are never updated; withdrawal logs a new row. `user_id` is
  `nullOnDelete` so the trail outlives the account.
- **Withdrawing while signed in signs the visitor out** (auth is cookie-backed):
  the endpoint logs out, invalidates the session, and the banner JS sends them
  home as a guest.
- **`RequireCookieConsent` middleware** wraps `/profile` and all five auth
  routes: anything but an `accepted` cookie redirects home with the
  `cookie_consent.required` flash, which force-opens the banner with a
  "sign-in and favorites need cookies" notice.
- **Equal prominence is a legal rule, not styling:** Accept and Decline share
  the single `.cookie-consent__btn` class (same size/color/font). Never restyle
  one of them, hide Decline, or pre-select anything.

## Profile & vendor management

`/profile` (`App\Livewire\Profile\ProfilePage`, `auth`-guarded) is the signed-in
home for two roles in one page. Views live in `resources/views/livewire/profile/`.

- **Eater by default, vendor on demand.** We never assume a user is a food-truck
  owner: the page shows their **favourited trucks** (a slim `.fav-list` of
  name-link + star rows, alphabetical — no cards, so no images to eager-load;
  the star is the same `<x-favorite-toggle>`, so rows don't vanish on tap, they
  go hollow and can be re-tapped), and only an explicit "Add a food truck" CTA
  (`addTruck()`) creates a `FoodTruck` tied to their user id. A user can own
  several.
- **Collapsed list → lazy editor.** Owned trucks render as collapsed
  `.truck-disclosure` cards (stub data: id + name only). Expanding one renders
  `<livewire:profile.truck-editor :truck-id … lazy />` — `TruckEditor` is
  `#[Lazy]`, so its full data (today's hours, menu, images, **cuisine tags**) loads
  in a **follow-up request** behind a `placeholder()` skeleton. The editable form
  is the nested `<x-truck-form>` Blade component.
- **Ownership is re-checked on every action.** `TruckEditor::truck()` does
  `findOrFail` + `abort_unless($truck->user_id === auth()->id(), 403)` and is
  called by mount **and** every mutating method — never trust the lazy snapshot.
- **Cuisine tags.** `TruckEditor` holds `$selectedTagIds` (array of tag IDs) and
  `$newTagName`. The form shows a pill-checkbox grid (`.tag-picker`) of all `Tag`
  rows; checked pills sync on `save()` via `$truck->tags()->sync(...)`. The "Add"
  button calls `addTag()`, which does `Tag::firstOrCreate(['slug' => Str::slug(…)])`
  and appends the new ID to `$selectedTagIds`. CSS-only active state via
  `:has(input:checked)` — no Alpine needed in the picker.
- **Saves are silent → confirmed by toast.** `save`/`uploadImage`/`deleteImage`/
  `deleteTruck` dispatch a `toast` browser event (`<x-toast>`); `save` also
  dispatches `truck-saved`/`truck-deleted` to `ProfilePage` to refresh the list.
- **Saving publishes.** `save()` sets `is_published = true` — a newly added truck
  (unpublished by default) goes live on its first save and appears in discovery.
- **Now Open + pin (real-time presence).** `goLiveNow()` stamps today's `opens_at` at
  the current truck-local moment (replaces a manual open-time input); `setLocation()`
  writes `latitude`/`longitude`/`located_at` from the browser's geolocation and
  reverse-geocodes `location_label`. Both capture the **browser IANA timezone** into
  `food_trucks.timezone` (validated against `timezone_identifiers_list()`), which the
  UTC-running app uses to show pin/open times in truck-local time. See *Maps & geolocation*.
- **Testing lazy components:** pass `['truckId' => …, 'lazy' => false]` to
  `Livewire::test()` so `mount()` runs immediately (see `tests/Feature/Profile/`).
  Geolocation/geocoding tests fake `Http` (Nominatim) and use `travelTo` for the clock.

### Normalized schema

Six create migrations (`2026_06_29_0000xx_*` + `2026_06_30_000001_*`), all `cascadeOnDelete` from the truck, plus one alter (`2026_07_01_000001_*` adds `food_trucks.timezone`):

| Table | Shape / decisions |
|---|---|
| `food_trucks` | `user_id` owner, `name`, `description`; nullable `latitude`/`longitude`/`location_label`/`located_at`/`timezone` (**the pin — set from the editor's Set-my-location CTA; `timezone` is the browser IANA zone captured with it**); `is_published` gates discovery |
| `truck_operating_hours` | One row **per business date** (`unique(food_truck_id, business_date)`) — vendors operate in real time day-by-day, **not** on a recurring weekly schedule. The editor only upserts **today's** row via `updateOrCreate` |
| `truck_images` | `path` to a normalized WebP on the public disk + `sort_order` |
| `menu_items` | `name`, `description`, `price_cents` (**money as integer cents, never float**), `is_available`, `sort_order` |
| `favorites` | `user_id`+`food_truck_id` pivot (`unique`). Toggled by `POST /api/favorites/{truck}` (see *Favorites*); read via `withExists`/`withCount` for stars and the Popular carousel |
| `tags` + `food_truck_tag` | Cuisine taxonomy. `tags`: `name`, `slug` (unique, auto-generated from name via `Str::slug()` on creating). `food_truck_tag`: composite PK pivot — no timestamps, cascade deletes on both FKs |

Models: `FoodTruck` (`user`, `operatingHours`, `todayHours`, `images`,
`menuItems`, `favoritedBy`, `tags`; plus `isOpenNow()` — true when now is within
today's window in the truck's timezone, or past an open time with no close set —
and `favoritedState()` — the card star's `favorited` prop: bool for a signed-in
user from the `is_favorited` withExists flag, null for guests),
`TruckOperatingHour`, `TruckImage` (`url` accessor),
`MenuItem` (`price` accessor), `Tag` (`foodTrucks`); `User` gained `foodTrucks()` and `favorites()`.

### Image pipeline

Uploads go through `App\Actions\StoreTruckImage` (uses **`intervention/image` v4**,
GD/Imagick both available in the container): `cover(250, 250)` (centre-crop to a
1:1 square) → `WebpEncoder(quality: 80)` → stored at
`truck-images/{truck}/{uuid}.webp` on the **public** disk. Re-encoding strips
EXIF/GPS metadata (privacy) and arbitrary file bytes; the component validates
`image|mimes:jpeg,png,webp` with the size cap in `TruckEditor::MAX_UPLOAD_KB` (5120).

> Image uploads need the public-disk symlink — run `lando artisan storage:link`
> once per environment (a fresh clone has no `public/storage`).

### Maps & geolocation

All mapping is **OpenStreetMap — free, no API key, no billing** (dep
`dantsu/php-osm-static-api` on PHP, `leaflet` on JS). Two rendering paths by where the
map centre is known:

- **Truck detail page — cached static PNG.** `App\Actions\GenerateTruckMapImage`
  renders OSM tiles (zoom 13, ~2.5-mile view) to a PNG on the **public** disk at
  `truck-maps/{truck}/{fingerprint}.png`. The **fingerprint** is a `sha1` of
  `(lat, lng, zoom, size)`, so moving the pin changes the path — the next page view
  regenerates and deletes the stale sibling (no schema, no cache table). Marker-free;
  the pin is a **CSS overlay** centred on the image. Failures return `null` and hide
  the map (never 500). Sends an identifying User-Agent (OSM tile policy).
- **Home page — interactive Leaflet.** `<x-truck-map>` / `truck-map.js` — centres on the
  visitor's GPS (unknowable server-side) with filterable pins; opens at a ~5-mile
  radius (`MAP_ZOOM = 12`) and the visitor can zoom freely. See *JavaScript / Alpine*.

**Reverse geocoding:** `App\Actions\ReverseGeocodeLabel` calls OSM **Nominatim** (keyless,
identifying User-Agent) to turn a pin into `location_label` ("Road, City"). Used by
`setLocation`; returns `null` on failure (a stale label is cleared rather than kept).

**Forward geocoding (ZIP/address fallback):** when a visitor declines browser
geolocation, `<x-location-search>` on the home page geocodes a typed ZIP/address via
`GET /api/geocode` (route name `geocode`), which wraps `App\Actions\GeocodeSearch`
(Nominatim `/search`, `countrycodes=us` to disambiguate bare ZIPs — widen if the app
expands abroad). The route caches each normalized query for a day and is throttled
(`throttle:15,1`) since a miss is an external request; no-match/failure returns 404.
The browser **never calls Nominatim directly** (it can't send the identifying
User-Agent the OSM policy requires). On success the form `$dispatch`es the same
`user-located` event the GPS path uses — bubbling, so the map and card sorting
(window listeners) need no special casing. The same component doubles as the truck
form's pin fallback when the vendor's GPS fails: an ancestor element catches the
bubbling event and feeds it into `TruckEditor::setLocation` (props override the
copy/visibility — see `resources/views/CLAUDE.md`). The route lives under `/api`
because `bootstrap/app.php` limits
`shouldRenderJsonWhen` to `api/*` — a JSON endpoint elsewhere would render
validation errors as redirects.

**Timezone:** the app runs in UTC. Open/close times are stored as naive truck-local
wall-clock and shown verbatim, so only the `located_at` timestamp is converted for display
— using `food_trucks.timezone` (the vendor's browser zone, captured on pin/Now-Open).

### Dev seed

`database/seeders/FoodTruckSeeder` (called by `DatabaseSeeder`) creates 3 vendor
users, 8 eater users, the full tag taxonomy, and 10 published trucks with menu items
and images. Every truck is pinned near ZIP 66202 (Mission, KS) — most within ~5 miles,
every third one an outlier up to 20 miles — with `timezone` `America/Chicago` (maps are
**not** pre-generated; the first page view renders and caches each). Each truck draws a
random crowd of eater favourites so the Popular carousel has a meaningful order out of
the box, and the smoke-test account (`test@example.com`) always favourites a few so
`/favorites` and the profile page have content on first sign-in. Fixture images live in
`database/seeders/fixtures/images/` and are processed through `StoreTruckImage` (same
pipeline as live uploads). Re-seed with:

```bash
lando artisan migrate:fresh --seed
```

## Favorites

Eaters star trucks; the pivot rows drive the star state everywhere, the Popular
carousel ranking, and the `/favorites` page. **Alpine + fetch, not Livewire** —
discovery pages are plain Blade, so the star is the anonymous `<x-favorite-toggle>`
component (rendered **only for signed-in users**; guests get no star at all).

- **One idempotent endpoint:** `POST /api/favorites/{truck}` (`favorites.toggle`,
  `auth` + throttled) does `$user->favorites()->toggle($truck)` and returns
  `{ favorited: bool }` — the button settles on that answer. Published trucks only
  (404 otherwise), matching their visibility everywhere else. Lives under `/api`
  so errors render as JSON (see the `/api/geocode` note).
- **Optimistic UI:** `favoriteToggle` (`resources/js/favorites.js`) flips the star
  on tap, then rolls back if the request fails — the UI never lies about persisted
  state. Initial state is server-rendered (`is_favorited` via `withExists`) so
  there's no flash before Alpine boots.
- **Surfaces:** discovery cards (star overlaid top-right — `<x-food-truck-card>`'s
  `truck-id` + `favorited` props; `favorited` null hides it), the truck detail
  page (in-flow beside the title, `@auth`-only), and the profile page's slim
  favourites list. Tests live in `tests/Feature/Favorites/`.

## Search

The header search bar (`<x-mobile-header>`) is a **plain GET form to `/search`**,
so Enter and the magnifier submit button work without JS; the `truckSearch` Alpine
component (`resources/js/search.js`) layers a **typeahead dropdown** on top.

- **One matching rule:** the `FoodTruck::search()` scope — truck **name**, cuisine
  **tag name**, or **menu item name** contains the term, published trucks only.
  Both the landing page and the typeahead use it, so they can never drift apart.
  `FoodTruck::likePattern()` escapes user-typed `%`/`_` so they match literally.
- **Typeahead:** `GET /api/search` (`search.suggest`, `min:2`, throttled) returns
  up to 8 trucks as `{ id, name, url, context }` — `url` links straight to the
  detail page; `context` is the matching tag or menu item when the truck's own
  name doesn't contain the term (null otherwise). The JS debounces input (300ms)
  and discards stale in-flight responses.
- **Landing page:** `/search?q=…` renders matching discovery cards, open-now first
  (same ordering as home); the header input stays pre-filled with the term.

Tests live in `tests/Feature/SearchTest.php`.

## Internal Docker hostnames

Services communicate by Docker service name, not `localhost`. Use these in config and `.env`:

| Service | Internal hostname | External (host machine) |
|---|---|---|
| MariaDB | `database` | `127.0.0.1:3306` |
| Redis | `cache` | `127.0.0.1:6379` |
| Reverb WebSocket | `reverb` | `localhost:8080` |

## Reverb host split — important

`REVERB_HOST=reverb` in `.env` is the **server-side** hostname — PHP in `appserver` uses `config/broadcasting.php → options.host` to connect to the Reverb container over the internal Docker network.

`VITE_REVERB_HOST=localhost` is hardcoded separately — the browser reaches Reverb on `localhost:8080`. It cannot inherit `REVERB_HOST` because Docker service names are not resolvable from the browser.

The host `8080` binding comes from an **explicit `ports: ['8080:8080']`** mapping on the `reverb` service in `.lando.yml` — **not** Lando's `portforward:` directive, which assigns a *random* host port for this custom service and silently breaks `localhost:8080`. Do not replace the explicit mapping with `portforward`.

The `reverb` service also **auto-runs `artisan reverb:start`** as its main process and stays up. Do not run `lando reverb:start` manually — it execs into the same container and collides on 8080 ("Address already in use").

Do not collapse `REVERB_HOST` and `VITE_REVERB_HOST` into one variable.

## Database credentials

```
DB_DATABASE=steet_bites
DB_USERNAME=steet_bites
DB_PASSWORD=steet_bites
```

Default Laravel recipe credentials (`laravel/laravel`) are overridden.

The connection driver is **`mariadb`** (not `mysql`) to match the MariaDB 10.11
backend — Laravel's dedicated driver, set in `.env` and as the `config/database.php`
default.

### Test database

`phpunit.xml` points the suite at a separate **`steet_bites_testing`** database on
the same MariaDB container (driver `mariadb`; host/user/password inherited from
`.env`). A `run_as_root` step on the `database` service in `.lando.yml` creates it
(idempotently) on every `lando start`, so a fresh clone needs no manual setup.

You never seed or sync data into it — DB-touching tests `use RefreshDatabase`,
which migrates the schema fresh and rolls back each test in a transaction. The
database only needs to *exist*; its contents are rebuilt automatically per run.

## Service URLs

| | URL |
|---|---|
| App | `https://steet-bites.lndo.site` |
| Mailpit UI | `http://localhost:8025` (or `https://mailpit.steet-bites.lndo.site`) |
| Reverb WebSocket (browser) | `ws://localhost:8080` |
| Vite dev server | `https://vite.steet-bites.lndo.site:5173` |

## Running Vite

```bash
lando npm run dev
```

Run Vite in the `node` service. The browser loads dev assets from
**`https://vite.steet-bites.lndo.site:5173`** — Vite serving **HTTPS directly**
on its published host port, **not** the Lando proxy. This took deliberate setup;
the moving parts (all already wired) must stay in sync:

1. **HTTPS, not HTTP.** The app is served over HTTPS, so http dev assets are
   blocked as mixed content. The `node` service sets `ssl: true` in `.lando.yml`,
   which makes Lando issue a cert (signed by the trusted Lando CA) at `/certs`.
   `vite.config.js` reads `/certs/cert.crt`+`cert.key` into `server.https`.
2. **Cert SAN must cover the browser hostname.** Lando derives a service's cert
   SANs from its **proxy hostnames**. A custom service's cert otherwise covers
   only `<service>.internal` / `node` / `localhost` / `127.0.0.1` — **not** the
   `*.lndo.site` name the browser uses — so Chrome rejects it
   (`NET::ERR_CERT_COMMON_NAME_INVALID`) and forces a manual bypass. The fix is a
   `node` entry in the `proxy:` block (`vite.steet-bites.lndo.site:5173`), present
   **solely** to inject that hostname into the cert SANs. After changing it,
   `lando rebuild -s node` reissues the cert; verify with
   `lando ssh -s node -c "openssl x509 -in /certs/cert.crt -noout -ext subjectAltName"`.
3. **Published port, not the proxy.** The Lando proxy will **not** route to
   Vite's custom port (returns a Traefik 404), so `node` publishes `5173:5173`
   via `overrides.ports`. The browser hits `vite.steet-bites.lndo.site` (which
   resolves to `127.0.0.1` via lndo.site DNS) on `:5173` directly. The `proxy:`
   entry from point 2 is **not** used for routing — only for the cert SAN — so
   the Traefik 404 never matters.
4. **`allowedHosts`.** Vite 8 returns `403 Blocked request` for non-allow-listed
   `Host` headers; `vite.config.js` allows `.lndo.site`.
5. **`origin`.** `server.origin` is `https://vite.steet-bites.lndo.site:5173`, so
   `laravel-vite-plugin` writes exactly that into `public/hot` and `@vite()`
   generates browser-correct asset URLs.

Do **not** revert any of these to a plain `localhost:5173` / proxy / http setup —
each one individually breaks asset loading (mixed-content block, Traefik 404,
403, or an unreachable `[::1]` in `public/hot`).

Do **not** use `lando composer dev` — the stock Laravel `dev` script runs bare
`vite` (no `--host`) and `artisan serve` inside the appserver container, which
writes an unreachable `http://[::1]:5173` into `public/hot` and bypasses the
nginx-served app. Run Vite (above) and the queue (`lando queue:work`) as separate
Lando commands instead; Reverb already runs automatically as the `reverb` service.

If assets ever 404 from a stale dev URL, delete `public/hot` to fall back to the
built manifest in `public/build`.

## Reverb container startup

The `reverb` Lando service polls for `/app/artisan` before starting `artisan reverb:start`. It auto-recovers once the Laravel install exists — no manual restart needed after `lando composer install`.
