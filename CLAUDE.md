# street-bites

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
rasters, `street-bites-icon-128.png` — a 128² resize of the 512² icon master used
wherever the mark renders small (map markers, the detail page's pin overlay; 128
covers 3× displays at a fraction of the bytes — don't point those at the master),
and `og-image.jpg` — the 1200×630 / 1.91:1 social share card, the default
`og:image`; keep re-exports under ~300 KB, the strictest crawler preview cap).
The SVGs are the masters (traced from the original art with potrace);
their fill is the logo's own brand red `#C72F2E` — close to but deliberately
**not** the `--color-accent-chili` token, so don't "fix" either to match the
other. Every full-page view opens `<head>` with `<x-seo-meta>` (title +
description + Open Graph link previews) and the favicon set (see
`resources/views/CLAUDE.md`).

Directory-local detail is in nested memory (loaded on demand — see *Nested memory*):

- **`resources/css/CLAUDE.md`** — the CSS partial map, design tokens, and the
  mustard/tangerine dark-text contrast rule.
- **`resources/views/CLAUDE.md`** — the Blade component table and its conventions
  (mobile-only positioning, inline-SVG icons, CSS scroll-snap).

The cross-cutting Alpine/Livewire rules and the route→view map stay here:

### JavaScript / Alpine

`resources/js/app.js` imports `truck-map.js` (the Leaflet home-page map),
`favorites.js`, and `search.js`. `echo.js` (the Reverb client) exists but is
**deliberately not imported** — nothing subscribes to `window.Echo` yet, and the
import drags laravel-echo + pusher-js (~90 KB minified) into every page; re-add
the import when the first realtime feature lands. **Do not import or start
Alpine here.** Livewire 4 bundles its own
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
OSM's tile max) with **Street Bites brand-icon markers** (`/images/street-bites-icon-128.png`
via `makeIcon(open)`, not an SVG divIcon; each pin payload carries `open` from
`$truck->isOpenNow()`, and closed trucks get a `truck-map__pin--closed` modifier that
dims the mark to muted grey so open/closed reads at a glance). Pins live in a
**`leaflet.markercluster` group**, not directly on the map, so trucks that stack up in
one spot (breweries, festivals, corporate lots) collapse into a brand-tinted count
bubble that expands on zoom — `refreshPins()` owns each marker's cluster-group
membership as the cuisine/radius filters change, and the "you are here" dot stays
un-clustered. `map.css` also restyles
Leaflet's default popup, zoom control, **and cluster bubble** to the design tokens
(unlayered overrides — see that file). `truckDistanceSort`
reorders a card list **open-first, then closest-first** (real DOM re-append) using each
card's `data-open` + `data-lat`/`data-lng`; `truckRadiusFilter` applies the radius cap
alone to lists that keep their server order (the Popular carousel, the search results
grid); `locationSearch(endpoint)` is the ZIP/address fallback form
(see *Maps & geolocation*). **Leaflet + markercluster (JS and CSS) are loaded via
dynamic `import()` inside `truckMap`'s async `init()`** (`loadLeaflet()` — Leaflet
first, then the plugin that patches it in place), so Vite splits them into their
own chunk that map-less pages (`/search`, `/about`, auth, profile) never download;
`app.js` itself carries none of Leaflet. Events that land during the chunk load are
safe — `filterPins`/`refreshPins` walk a still-empty `this.markers`. The visitor's
position flows through two **window events** that decouple the pieces:
`user-located` `{ lat, lng }` (dispatched by the GPS success callback **and** by
`locationSearch` — `truckMap` listens and recenters/moves the "you are here" dot,
`truckDistanceSort` reorders) and `user-location-denied` (dispatched when GPS is
declined, missing, **or times out** — both `getCurrentPosition` call sites pass a
10-second `timeout` (`GEO_TIMEOUT_MS` here, inline in the truck form's
Set-my-location CTA) because a browser whose OS location service can't produce a
fix (macOS logs `kCLErrorLocationUnknown`) may otherwise never invoke either
callback, leaving the fallback hidden forever — `locationSearch` reveals itself
on it).

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

**Distance readouts:** `refreshDistances()` (module-level window listeners, not an Alpine
component) fills every `.truck-distance` element with a "X miles away" label via
`formatMiles` (tenths under 10 miles, whole beyond, "Less than 0.1 miles away" when almost
on top, singular "mile" at exactly 1) once a location is known — reading coordinates from
each element's nearest `[data-lat]` ancestor (the discovery-card wrapper on card grids, the
element itself on the truck detail page). Wired to the same `user-located` event and
session-remembered location as the sort/cap; hidden until a location arrives and for
unpinned trucks (unknown ≠ near). Purely presentational — the radius cap and open-first
sort are handled separately. Surfaces: `.food-truck-card__distance` (between the card title
and CTA) and `.truck-page__distance` (under the detail page's location line).

Three more Alpine components follow the same register-on-`alpine:init` pattern:
`favoriteToggle(endpoint, favorited, csrf)` (`resources/js/favorites.js`) powers the
`<x-favorite-toggle>` star — optimistic flip, then settles on the JSON answer from
`POST /api/favorites/{truck}`, rolling back on failure; `reportToggle(endpoint,
reported, csrf)` (`resources/js/report.js`) powers the `<x-report-truck>` megaphone —
a one-way commit (no un-report), so **not** optimistic: it flips to "Reported" only
once `POST /api/trucks/{truck}/report` confirms (see *Content moderation*, Public
reports); `truckSearch(endpoint, initial)` (`resources/js/search.js`) is the header
search typeahead (see *Search*). All three are imported by `app.js` alongside
`truck-map.js`.

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
- `/trucks/{truck}/{slug}` → `trucks/show.blade.php` (`trucks.show`, `whereNumber`
  on the id) — the **public truck detail page** discovery cards link to. The id
  identifies the truck; the slug is the name-derived `FoodTruck::slug` accessor
  (`Str::slug(name)`, no column — always current after a rename) and **must match
  exactly or the page 404s**, so every URL that renders is its own canonical
  (`<x-seo-meta>` canonicalises to the current URL — no redirects, no duplicates).
  Generate links with `route('trucks.show', [$truck, $truck->slug])`. Plain Blade
  view (no Livewire): cached OSM static map with a centred pin, a **"Get
  directions"** CTA under it, cuisine tags, today's hours ("Open today …" / "Open
  now — since …" / unposted), location, the **"Follow …" social-icon row**
  (`<x-social-links>`, hidden when the truck has none), and menu. The **Get
  directions** button is a mustard `.btn` linking to a Google Maps
  `dir/?api=1&destination={lat},{lng}` deep link — **no origin**, so Google routes
  from the visitor's current location and deep-links the native maps app on mobile;
  it's gated on the truck having a pin (not on `$mapUrl`, so directions survive a
  static-map render failure). A quiet **`<x-report-truck>` megaphone** sits in the
  footer (any visitor can flag the truck as offensive — surface-only; hidden for the
  owner and admins see their **Moderation** panel below it). The route eager-loads
  `images`, `tags`, `menuItems`, `todayHours`, and `socialLinks`, passes `$mapUrl`
  from `GenerateTruckMapImage`, and computes `$isFavorited` / `$isReported` for
  signed-in visitors. Unpublished/missing trucks 404. See *Content moderation*
  (Public reports), *Maps & geolocation*, and *Social links*.
- `/news` → `news/index.blade.php` (`news.index`) — the **news & events landing page**:
  a full-size search page for posts that doubles as the feed — an empty `q` lists
  every published post newest-first (unlike `/search`, which prompts). The page
  carries its **own GET search form** (the header search stays trucks-only);
  matching is title or body via `Post::search()`. Bare listing is indexable;
  query-filtered views pass `robots="noindex"`. See *News & events*.
- `/news/{post}/{slug}` → `news/show.blade.php` (`news.show`, `whereNumber` on the
  id) — the **public story page**, an exact `trucks.show` mirror: published-only
  `findOrFail`, the slug is the title-derived `Post::slug` accessor and **must
  match exactly or the page 404s** (every rendering URL is its own canonical).
  Generate links with `route('news.show', [$post, $post->slug])`. Plain Blade:
  optional cover (also the og:image, via the shell layout's `image`/`type`
  props), byline date, an event call-out when `event_date` is set, and the
  safe-mode-rendered Markdown body (`Post::bodyHtml()`). See *News & events*.
- `/about` → `about.blade.php` (`about`) — public **"About us"** page: the mission
  (founding question as a pull-quote), the developer intro, and follow-along link
  cards (YouTube / GitHub / LinkedIn) — plus, when configured, an optional
  **Buy Me a Coffee** support card appended to that grid (see *Support link*).
  Static `Route::view` on the shell layout, linked from the hamburger menu
  (`active="about"`); bespoke visuals in `resources/css/components/about.css`.
- `/sitemap.xml` → `sitemap.blade.php` (`sitemap`) — the **XML sitemap** search
  engines fetch, advertised by `public/robots.txt` (whose `Sitemap:` line is the
  absolute production URL — the directive requires one; robots.txt also disallows
  the non-indexable surfaces: profile, favorites, auth, api, search, styleguide).
  Generated fresh on every request — no cron, no cached file to rebuild — so
  newly published content is in the very next fetch: home, `/about`, `/news`, and
  every published truck's and news post's canonical slug URL with `lastmod` = the
  row's `updated_at`. The route builds `['loc' => …, 'lastmod' => ?]` entries and
  the view only prints the `<urlset>` body, so future public surfaces join by
  `concat()`ing their own entries in the route. Plain XML view — no layout, no
  `<x-seo-meta>`. **The `<?xml` prolog is prepended in the route (plain PHP), never
  written in the Blade view** — a literal prolog compiles to a cached PHP file
  whose open-tag bytes a `short_open_tag=On` server (prod) mis-parses, 500ing the
  page. Same trap in PHP `//` comments: a `?>` ends the comment, so keep both out
  of the route file too.
- `/.well-known/security.txt` → route in `routes/web.php` (`security.txt`) — the
  **RFC 9116 vulnerability-disclosure file**. A route (not a static file), mirroring
  the sitemap, so `Expires` rolls forward on every fetch and never goes stale.
  `Contact` is `security@street-bites.org` (a Cloudflare Email Routing forwarder to
  the maintainer inbox). nginx already permits `/.well-known` (its dotfile deny rule
  excludes it), so it falls through to `index.php`. See *Security headers*.
- `/profile` → `App\Livewire\Profile\ProfilePage` (`auth` middleware) — the
  signed-in profile (see *Profile & vendor management* below). Uses the
  `layouts/shell.blade.php` layout, which factors the welcome shell's chrome
  (fixed header + bottom nav + `<x-toast>`) into a reusable layout for app pages.
- `/admin/trucks` → `App\Livewire\Admin\ModerationQueue` (`auth` + `EnsureAdmin` +
  consent) — the **content-moderation queue** (see *Content moderation*): every
  truck newest-first with review / removed / reinstatement tabs, the editable
  blocklist panel, the cuisine-tag deletion panel, and
  approve / remove / restore / block-vendor + reinstate/dismiss actions. Admin-only;
  disallowed in `robots.txt`. Sibling **plain CSRF POST** routes
  `admin.trucks.remove` / `admin.trucks.block` back the admin panel on the public
  truck detail page (`trucks/show`).
- `/admin/news` → `App\Livewire\Admin\NewsManager` (same `auth` + `EnsureAdmin` +
  consent stack) — the **news & events authoring page**, the only way posts are
  created or edited (see *News & events*): one editor form (Markdown body,
  optional event date/location, optional cover upload) plus the full post list
  with publish / unpublish / edit / remove-cover / delete actions.
- `/styleguide` → `styleguide.blade.php` — living style guide demoing every token
  and component in isolation (route in `routes/web.php`).

## Authentication

Three email-verified flows live under `app/Livewire/Auth/` (full-page Livewire
components; views in `resources/views/livewire/auth/`). State passes between steps
via the session; the user is logged in only at the final step.

| Flow | Steps (route → component) | Notes |
|---|---|---|
| Sign-up | `auth.email` → `auth.verify` → `auth.password` (`EmailEntry` → `VerifyCode` → `SetPassword`) | Verify email via 6-digit code, then set a password and create the account |
| Sign-in | `auth.login` → `auth.login.verify` (`Login` → `LoginVerify`) | Password (primary factor) → emailed 6-digit code (second factor) → home |
| Password reset | `auth.password.request` → `auth.password.verify` → `auth.password.reset` (`ForgotPassword` → `ResetVerify` → `ResetPassword`) | "Forgot password?" on the login form → email a code → verify → set a new password and sign in. Reuses the same OTP engine; email ownership stands in for the forgotten password |

- **One-time codes:** `App\Models\EmailVerification` stores a **hashed** 6-digit
  code per email (10-min TTL, 5-attempt cap, burned on success/exhaustion). Mailed
  via `EmailVerificationCode` (sign-up — includes an auto-verifying signed magic
  link to `auth.verify`), `LoginCode` (sign-in — code only, **no** magic link, so
  it can't drop the user into the sign-up flow), and `PasswordResetCode` (reset —
  likewise code only, no link, so a click can't misroute into another flow).
- **Email-as-2FA is deliberate** — the second factor stays email OTP (not
  TOTP/SMS) to limit PII and complexity. Don't swap it without a product decision.
- **Stepped auth + anti-enumeration:** `Login` validates the password first, then
  hands off; a single generic error covers both unknown email and wrong password,
  and the code step gives a generic "invalid or expired" error. Every auth step
  is rate-limited through the shared `ThrottlesAttempts` trait
  (`app/Livewire/Auth/Concerns/`) — one default policy (5 attempts / rolling
  minute) and one generic "slow down" error for all flows. **Sign-up code
  sending is double-capped** (it's the one flow that mails an arbitrary
  visitor-typed address — a bounce/abuse surface for the mail provider): on top
  of the per-email bucket, `EmailEntry::submit()` and `VerifyCode::resend()`
  share a per-IP bucket (`EmailEntry::SEND_IP_MAX_ATTEMPTS` — 10/rolling hour)
  so rotating addresses can't pump mail, and `EmailEntry` validates
  `email:rfc,dns` (MX/A lookup) so typo domains never get a send — the dns rule
  is skipped under the test suite (`runningUnitTests()`, same pattern as the
  HIBP check). Pending sign-in is tracked by
  `session('auth.login.pending')` = the user id only — never the password. The
  **reset** flow's request step (`ForgotPassword`) is anti-enumeration too: it
  always advances to the code screen with the same message and only actually mails
  a code when the email belongs to a real account. Reset state lives in its own
  `auth.reset.email` / `auth.reset.verified` session keys (kept separate from
  sign-up's `auth.email` / `auth.verified` so the flows never cross-contaminate);
  `ResetPassword` re-checks the verified email maps to a real account before
  updating the password, then signs the user in (mirrors sign-up's `SetPassword`).
- **Sign out** is a plain CSRF-protected `POST /logout` (route name `logout`,
  `auth`-guarded) — not a Livewire action, so the session teardown
  (`logout` → `invalidate` → `regenerateToken` → redirect home) is a full request,
  not an AJAX round-trip. It's deliberately **outside** `RequireCookieConsent`
  (logging out must always work), and reachable from two surfaces: the profile
  page's account footer and the `<x-mobile-header>` hamburger menu (both `@auth`-only
  forms posting to `route('logout')`). Same teardown the cookie-withdrawal endpoint
  uses.
- **Delete account** is a sibling plain CSRF `POST /account/delete` (route name
  `account.destroy`, `auth`-guarded) in the profile footer — same full-request
  pattern as logout (clear the public-disk assets, delete the user, then
  `logout` → `invalidate` → `regenerateToken` → redirect home). Deleting the user
  hard-deletes every truck they own through the `food_trucks.user_id`
  `cascadeOnDelete` FK (and, cascading from each truck, its hours, images, menu
  items, tag pivots, social links, and favourite rows); `cookie_consents.user_id`
  is `nullOnDelete` so the GDPR audit trail outlives the account. The route also
  clears each truck's `truck-images/{id}` + `truck-maps/{id}` directories
  (`withTrashed`, so admin-removed trucks' files go too) since the DB cascade never
  touches the disk. Irreversible, so the profile UI gates it behind an Alpine
  confirm step (a `btn-accent` "Permanently delete" inside a reveal). No
  re-auth/OTP step — same trust level as the other profile actions.

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
- **`RequireCookieConsent` middleware** wraps `/profile` and all eight auth
  routes (sign-up, sign-in, and password-reset steps): anything but an `accepted`
  cookie redirects home with the
  `cookie_consent.required` flash, which force-opens the banner with a
  "sign-in and favorites need cookies" notice.
- **Equal prominence is a legal rule, not styling:** Accept and Decline share
  the single `.cookie-consent__btn` class (same size/color/font). Never restyle
  one of them, hide Decline, or pre-select anything.

## Security headers

Response-header hardening lives in **`App\Http\Middleware\SecurityHeaders`**,
appended to the `web` group in `bootstrap/app.php`. **The app is the only place
these can be set** — Cloudflare is DNS-only (grey cloud) and the ALB injects no
response headers, so neither upstream can add them. Applied to every web response:
`X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY` (the app never frames
itself), `Referrer-Policy: strict-origin-when-cross-origin`, and a
`Permissions-Policy` that scopes `geolocation` to `self` (the discovery map uses
it) and denies camera/microphone/payment.

- **HSTS** (`Strict-Transport-Security: max-age=31536000; includeSubDomains`) is
  emitted **only over HTTPS and never in local** (`$request->isSecure() && !
  App::environment('local')`). It's actively harmful under Lando: local is HTTPS
  with Lando's self-signed cert, and an HSTS pin turns the normally-bypassable cert
  warning into a **hard block with no "proceed anyway"** (if you ever hit that,
  clear it at `chrome://net-internals/#hsts`). `preload` is **deliberately left
  off** — it forces every current and future subdomain to HTTPS forever, so adding
  it (and submitting to hstspreload.org) is a separate, hard-to-reverse decision.
- **Content-Security-Policy** (HTML responses only — skipped on JSON/XML/plain-text
  like the API, sitemap, and security.txt) is built by `contentSecurityPolicy()`
  from an audit of what the app loads: `script-src 'self' 'unsafe-eval'` —
  `'unsafe-eval'` is required because Livewire 4 bundles Alpine (Function-constructor
  expression compilation), and there are **no inline `<script>` blocks in views**, so
  scripts deliberately get **no `'unsafe-inline'`**. `style-src 'self' 'unsafe-inline'`
  (both `@livewireStyles` and `@fonts` emit inline `<style>`). `img-src` allows OSM
  tiles (`tile.openstreetmap.org`), `data:`, and — in prod — the S3 public-disk host
  (parsed from `config('filesystems.disks.s3.url')`). `connect-src 'self'`
  (favorites/search/geocode/report and Livewire's update endpoint are same-origin;
  Reverb's wss isn't wired up yet). In **local dev** the Vite dev-server origin from
  `public/hot` (plus its `wss://` HMR socket) is added to script/style/font/connect.
- **Report-only until enforced.** The CSP ships as
  `Content-Security-Policy-Report-Only` (logs violations, blocks nothing) until
  `config('security.csp_enforce')` — env `CSP_ENFORCE` (`config/security.php`) — is
  true, which flips the header name to the enforcing `Content-Security-Policy`. Flip
  it only after watching the browser console for violations across the map,
  favorites, search, auth, and admin flows. No code change to change modes.
- **`/.well-known/security.txt`** (see *Pages*) is the paired RFC 9116 disclosure
  file. Prod-only companions live in the Docker image, not Lando (`recipe: laravel`):
  `server_tokens off` (`docker/nginx.conf`) and `expose_php = Off`
  (`docker/php.ini`, a conf.d drop-in) trim version-leaking `Server`/`X-Powered-By`
  headers. Tests: `tests/Feature/SecurityHeadersTest.php`.

## Profile & vendor management

`/profile` (`App\Livewire\Profile\ProfilePage`, `auth`-guarded) is the signed-in
home for two roles in one page. Views live in `resources/views/livewire/profile/`.
A config-gated **Buy Me a Coffee** support callout (`.profile__support`) sits under
the heading, deliberately quieter than the "Add a food truck" CTA (see *Support link*).

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
  `#[Lazy]`, so its full data (today's hours, menu, images, **cuisine tags**,
  **social links**) loads in a **follow-up request** behind a `placeholder()`
  skeleton. The editable form is the nested `<x-truck-form>` Blade component.
- **Ownership is re-checked on every action.** `TruckEditor::truck()` does
  `findOrFail` + `abort_unless($truck->user_id === auth()->id(), 403)` and is
  called by mount **and** every mutating method — never trust the lazy snapshot.
- **Cuisine tags.** `TruckEditor` holds `$selectedTagIds` (array of tag IDs) and
  `$newTagName`. The form shows a pill-checkbox grid (`.tag-picker`) of all `Tag`
  rows; checked pills sync on `save()` via `$truck->tags()->sync(...)`. The "Add"
  button calls `addTag()`, which does `Tag::firstOrCreate(['slug' => Str::slug(…)])`
  and appends the new ID to `$selectedTagIds`. **A blocklisted tag name is denied
  outright** (`tagNameBlocked()` — both `addTag()` and the typed-but-not-Added path
  in `save()`): tags are shared public taxonomy, so there's no held-for-review
  middle ground — the row is never created and the vendor gets a `newTagName`
  validation error. The slug is screened alongside the raw name ("c.u.m" → "cum").
  CSS-only active state via `:has(input:checked)` — no Alpine needed in the picker.
- **Social links.** `TruckEditor` holds `$socialLinks` (array of `['id', 'url']`
  rows). The form shows repeatable URL inputs (`addSocialLink()`/`removeSocialLink()`,
  same reconcile-on-save pattern as the menu); each URL is validated `url:http,https`.
  `save()` calls `saveSocialLinks()`, which `updateOrCreate`s each row (stamping
  `sort_order` from position and `platform` from `SocialPlatform::fromUrl($url)`) and
  deletes any rows the vendor removed. **The vendor never picks a platform** — it's
  detected from the pasted URL's host. See *Social links*.
- **Saves are silent → confirmed by toast.** `save`/`uploadImage`/`deleteImage`/
  `deleteTruck` dispatch a `toast` browser event (`<x-toast>`); `save` also
  dispatches `truck-saved`/`truck-deleted` to `ProfilePage` to refresh the list.
- **Saving publishes — unless screening holds it.** `save()` auto-screens the truck
  (text via a word-list, images already screened at upload) and sets `is_published
  = true` only when clean; a flagged truck is held (`screen_status = 'flagged'`,
  unpublished) for admin review, and a banned vendor is blocked from publishing
  altogether. A banned vendor's profile shows a **"Request reinstatement" form**
  (`requestReinstatement()`) in place of the "Add a food truck" CTA. See
  *Content moderation*.
- **Now Open / Closing Up + pin (real-time presence).** `goLiveNow()` stamps today's
  `opens_at` at the current truck-local moment (replaces a manual open-time input) and
  `closeNow()` is its mirror — stamps today's `closes_at` at the current moment so
  `isOpenNow()` flips to closed immediately (a null `closes_at` otherwise reads as
  "still open"). Both drive the two hours-section CTAs; the manual `closesAt` time input
  stays for pre-setting a planned close. `setLocation()`
  writes `latitude`/`longitude`/`located_at` from the browser's geolocation and
  reverse-geocodes `location_label`. All three capture the **browser IANA timezone** into
  `food_trucks.timezone` (validated against `timezone_identifiers_list()`), which the
  UTC-running app uses to show pin/open times in truck-local time. See *Maps & geolocation*.
- **Testing lazy components:** pass `['truckId' => …, 'lazy' => false]` to
  `Livewire::test()` so `mount()` runs immediately (see `tests/Feature/Profile/`).
  Geolocation/geocoding tests fake `Http` (Nominatim) and use `travelTo` for the clock.

### Normalized schema

Seven create migrations (`2026_06_29_0000xx_*` + `2026_06_30_000001_*` + `2026_07_05_000001_*`), all `cascadeOnDelete` from the truck, plus alters (`2026_07_01_000001_*` adds `food_trucks.timezone`; the `2026_07_06_*` set adds content-moderation columns and a standalone `moderation_terms` table — see *Content moderation*):

| Table | Shape / decisions |
|---|---|
| `food_trucks` | `user_id` owner, `name`, `description`; nullable `latitude`/`longitude`/`location_label`/`located_at`/`timezone` (**the pin — set from the editor's Set-my-location CTA; `timezone` is the browser IANA zone captured with it**); `is_published` gates discovery. Moderation adds `screen_status` (auto-screen result), `reviewed_at` (admin sign-off), `moderation_reason`, and `SoftDeletes` (`deleted_at`) so a removed truck is hidden everywhere but retained as evidence — see *Content moderation* |
| `truck_operating_hours` | One row **per business date** (`unique(food_truck_id, business_date)`) — vendors operate in real time day-by-day, **not** on a recurring weekly schedule. The editor only upserts **today's** row via `updateOrCreate` |
| `truck_images` | `path` to a normalized WebP on the public disk + `sort_order`; `screen_status` + `flag_labels` hold the per-image auto-screen result (see *Content moderation*) |
| `menu_items` | `name`, `description`, `price_cents` (**money as integer cents, never float**), `is_available`, `sort_order` |
| `favorites` | `user_id`+`food_truck_id` pivot (`unique`). Toggled by `POST /api/favorites/{truck}` (see *Favorites*); read via `withExists`/`withCount` for stars and the Popular carousel |
| `tags` + `food_truck_tag` | Cuisine taxonomy. `tags`: `name`, `slug` (unique, auto-generated from name via `Str::slug()` on creating). `food_truck_tag`: composite PK pivot — no timestamps, cascade deletes on both FKs |
| `truck_social_links` | `url` + `platform` + `sort_order`. The vendor only pastes a `url`; `platform` is **derived from its host on save** (`App\Enums\SocialPlatform::fromUrl`, cast to the enum) so the detail page renders the brand icon without re-parsing. Unrecognised hosts store `Website` (generic globe). See *Social links* |
| `moderation_terms` | Admin-managed blocklist words (`term`, unique), layered on top of the env/config baseline. Edited from the moderation page, effective immediately (`ModerationTerm` busts a cache on every write). Not truck-scoped. See *Content moderation* |
| `posts` | News & events (one type — an `event_date` makes a post an event; see *News & events*): `user_id` author (**nullable, `nullOnDelete`** — posts are site content and outlive the account, unlike trucks' cascade), `title`, `body` (Markdown source), nullable `event_date` (**pure date** — times belong in the body; dodges the UTC/timezone problem) + `event_location` label, nullable `cover_image_path` (1200×630 JPEG), `is_published`, `published_at` (stamped on first publish, never reset — the byline + `/news` sort key). **No SoftDeletes** — authors are the moderators |
| `reinstatement_requests` | A blocked vendor's request to have their ban lifted (`2026_07_10_000001_*`): `user_id` (`cascadeOnDelete`), nullable `message`, `status` (`pending`/`approved`/`dismissed`), nullable `reviewed_at`. Its own table (not a users column) so the request carries the vendor's message + an audit trail of each decision. Created from the profile, reviewed on the moderation queue's Reinstatement tab — see *Content moderation* |
| `truck_reports` | Public "this truck is offensive" flags (`2026_07_10_000002_*`): `food_truck_id` (`cascadeOnDelete`), nullable `user_id` (`nullOnDelete` — signed-in reporter), nullable `reporter_hash` (**hashed IP** for anonymous dedupe/abuse), `status` (`open`/`dismissed`), nullable `reviewed_at`. Written by `POST /api/trucks/{truck}/report` (guest-accessible); open rows surface the truck on the moderation queue's Reported tab. **Surface-only — a report never unpublishes the truck.** See *Content moderation* |

Plus `users` gained nullable `banned_at` + `ban_reason` (the vendor block — see *Content moderation*).

Models: `FoodTruck` (`user`, `operatingHours`, `todayHours`, `images`,
`menuItems`, `favoritedBy`, `tags`, `socialLinks`; plus `isOpenNow()` — true when
now is within today's window in the truck's timezone, or past an open time with no
close set — and `favoritedState()` — the card star's `favorited` prop: bool for a
signed-in user from the `is_favorited` withExists flag, null for guests),
`TruckOperatingHour`, `TruckImage` (`url` accessor),
`MenuItem` (`price` accessor), `Tag` (`foodTrucks`),
`TruckSocialLink` (`foodTruck`; `platform` cast to the `App\Enums\SocialPlatform`
enum), and `ModerationTerm` (admin-managed blocklist words — see *Content
moderation*), `Post` (`user`; `isEvent()`, the derived `slug`/`excerpt`/
`bodyHtml` accessors and `coverUrl()` — see *News & events*), `ReinstatementRequest` (`user`; the `STATUS_*` constants — see *Content
moderation*), and `TruckReport` (`foodTruck`, `user`; the `STATUS_*`
constants — the public report rows, see *Content moderation*); `FoodTruck`
also gained `reports()`. `User` gained
`foodTrucks()`, `favorites()`, `reinstatementRequests()`, plus `isAdmin()`
(config email allowlist), `isBanned()` (`banned_at`), and
`hasPendingReinstatementRequest()`. `FoodTruck` uses
`SoftDeletes`, so every discovery query already excludes admin-removed trucks.
`likePattern()` (LIKE-wildcard escaping) lives in the shared
`App\Models\Concerns\EscapesLikePatterns` trait, used by `FoodTruck` and `Post`.

### Image pipeline

Uploads go through `App\Actions\StoreTruckImage` (uses **`intervention/image` v4**,
GD/Imagick both available in the container): `cover(250, 250)` (centre-crop to a
1:1 square) → `WebpEncoder(quality: 80)` → stored at
`truck-images/{truck}/{uuid}.webp` on **`config('filesystems.public_disk')`**
(`public` locally, `s3` in production — see *Deployment (AWS)*). Re-encoding strips
EXIF/GPS metadata (privacy) and arbitrary file bytes; the component validates
`image|mimes:jpeg,png,webp` with the size cap in `TruckEditor::MAX_UPLOAD_KB` (5120).
On upload the stored image is also run through `App\Actions\ScreenImage` (AWS
Rekognition, off unless `MODERATION_REKOGNITION_ENABLED`) and the result written to
`truck_images.screen_status`; a flagged image holds its truck out of discovery until
an admin approves it. See *Content moderation*.

> Image uploads need the public-disk symlink — run `lando artisan storage:link`
> once per environment (a fresh clone has no `public/storage`).

### Maps & geolocation

All mapping is **OpenStreetMap — free, no API key, no billing** (dep
`dantsu/php-osm-static-api` on PHP, `leaflet` + `leaflet.markercluster` on JS). Two
rendering paths by where the map centre is known:

- **Truck detail page — cached static PNG.** `App\Actions\GenerateTruckMapImage`
  renders OSM tiles (zoom 13, ~2.5-mile view) to a PNG on
  **`config('filesystems.public_disk')`** at
  `truck-maps/{truck}/{fingerprint}.png`. The **fingerprint** is a `sha1` of
  `(lat, lng, zoom, size)`, so moving the pin changes the path — the next page view
  regenerates and deletes the stale sibling (no schema, no cache table). Marker-free;
  the pin is a **CSS overlay** — the same Street Bites brand icon as the home markers,
  centred with its tip at the image centre (`.truck-page__pin`, `truck-page.css`).
  Attribution ("© OpenStreetMap contributors") is **baked into the image** by the
  `TileLayer` credit, so the detail page shows **no separate caption** — it relies on
  the in-image credit the same way the home map relies on Leaflet's own control.
  Failures return `null` and hide the map (never 500). Sends an identifying User-Agent
  (OSM tile policy).
- **Home page — interactive Leaflet.** `<x-truck-map>` / `truck-map.js` — centres on the
  visitor's GPS (unknowable server-side) with filterable brand-icon pins (open vivid,
  closed dimmed grey) that **cluster** into a count bubble where trucks group up
  (`leaflet.markercluster`); opens at a ~5-mile radius (`MAP_ZOOM = 12`) and the visitor
  can zoom freely. See *JavaScript / Alpine*.

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
users, 8 eater users, the full tag taxonomy, and 10 published trucks with menu items,
images, and 1–3 social links each (platforms derived from the seeded URLs via
`SocialPlatform::fromUrl`). Every truck is pinned near ZIP 66202 (Mission, KS) — most within ~5 miles,
every third one an outlier up to 20 miles — with `timezone` `America/Chicago` (maps are
**not** pre-generated; the first page view renders and caches each). Each truck draws a
random crowd of eater favourites so the Popular carousel has a meaningful order out of
the box, and the smoke-test account (`test@example.com`) always favourites a few so
`/favorites` and the profile page have content on first sign-in. Fixture images live in
`database/seeders/fixtures/images/` and are processed through `StoreTruckImage` (same
pipeline as live uploads). `NewsSeeder` follows it, seeding the `/news` section
(six published posts — two events — plus a draft, covers via `StorePostCoverImage`;
see *News & events*). Re-seed with:

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

## News & events

One content type (`Post`) covers both: a post with an `event_date` is an event, one
without is a plain story. Public surfaces are `/news` (search + newest-first feed
in one page) and `/news/{post}/{slug}` (see *Pages*); nav is the header-only
**News** entry in `<x-mobile-header>` (like About — deliberately **not** a bottom
`<x-mobile-nav>` tab). Published posts join the sitemap via the route's `concat()`.

- **Authoring is admin-only, via a form — deliberately not an API.** `/admin/news`
  (`App\Livewire\Admin\NewsManager`) sits behind the same `auth` + `EnsureAdmin`
  (the `ADMIN_EMAILS` allowlist) + consent stack as the moderation queue; every
  action re-checks `isAdmin()` through the shared
  `App\Livewire\Admin\Concerns\AuthorizesAdmin` trait (also used by
  `ModerationQueue`). An IP-allowlisted API was considered and rejected: behind
  the ALB the client IP is an `X-Forwarded-For` header, residential IPs churn,
  and it would add a token/client to manage — while the admin form already rides
  the email-OTP 2FA sign-in. **No moderation pass** on posts: the authors *are*
  the moderators (also why `posts` has no SoftDeletes — no evidence trail needed;
  `deletePost` clears the cover directory and hard-deletes).
- **The body is Markdown, rendered in safe mode.** `Post::bodyHtml()` wraps
  `Str::markdown($body, ['html_input' => 'strip', 'allow_unsafe_links' => false])`
  (CommonMark ships with Laravel — no extra dependency), so raw HTML in the source
  is stripped and `javascript:` links dropped — the story page's `{!! !!}` print is
  not an XSS surface. Rendered on the fly, deliberately uncached (admin-authored
  volume). `.news-page__body` in `news.css` owns the prose rules Tailwind preflight
  strips. `slug` (title-derived, no column — mirrors `FoodTruck::slug`) and
  `excerpt` (rendered body, tags stripped, 160 chars — the teaser and meta
  description) are accessors, so they never go stale.
- **Draft → publish is a two-step.** `save()` creates/updates (new posts are
  drafts); `publish()` flips `is_published` and stamps `published_at` **only on
  the first publish** (re-publishing after an unpublish keeps the original byline
  date). `/news` orders by `published_at` desc.
- **The cover is one 1200×630 JPEG that doubles as og:image.**
  `App\Actions\StorePostCoverImage` (a `StoreTruckImage` sibling): centre-crop to
  1200×630 → `JpegEncoder(quality: 75)` → `post-covers/{post}/{uuid}.jpg` on
  `config('filesystems.public_disk')`. JPEG (not WebP) on purpose — it's the one
  format every link-preview crawler renders, and q75 stays under the ~300 KB
  crawler cap. Replacing/removing a cover deletes the old file (the caller's
  job — the action only stores). The story page passes `coverUrl()` +
  `type="article"` through the shell layout's `image`/`type` props (added for
  this — they forward to `<x-seo-meta>`); no cover → the default branded card.
- **Search:** `Post::search()` (title or body contains) mirrors
  `FoodTruck::search()`, sharing the `EscapesLikePatterns` trait. No typeahead —
  `/news`'s own GET form is the whole search surface.

Env: nothing new. Tests: `tests/Feature/News/` (listing/search + story page,
including the HTML-strip and unsafe-link assertions) and
`tests/Feature/Admin/NewsManagerTest.php` (access control, CRUD, publish
stamping, cover pipeline). `NewsSeeder` (called by `DatabaseSeeder`) seeds six
published posts — two events, real Markdown bodies, a few covers — plus a draft.

## Content moderation

Onboarding is deliberately frictionless — any signed-in user adds and publishes a
truck instantly. The moderation layer keeps that fast path for clean content while
catching offensive text/images, without gating every truck behind manual review.

- **Admins are a config email allowlist**, not a DB role: `ADMIN_EMAILS`
  (comma-separated) → `config('admin.emails')` → `User::isAdmin()`
  (case-insensitive). No migration, trivial per-environment. `EnsureAdmin`
  middleware guards the `/admin` route group, and every `ModerationQueue` action
  re-checks `isAdmin()` (same defence-in-depth as `TruckEditor::truck()`).
- **Auto-screen on the way to publish (hold-flagged-only).** `TruckEditor::save()`
  runs `App\Actions\ScreenText` (a whole-word, case-insensitive blocklist match over
  name/description/menu text) and checks whether any image was flagged at upload by
  `App\Actions\ScreenImage`. Clean → publishes instantly, exactly as before; flagged
  → held (`is_published = false`, `screen_status = 'flagged'`, `reviewed_at` cleared,
  a `moderation_reason`) with an "in review" toast. The image screen is **AWS
  Rekognition** `DetectModerationLabels`, **off unless `MODERATION_REKOGNITION_ENABLED`**
  and **fail-open** (disabled or erroring → treated as clean; the admin queue is the
  backstop). It runs once at upload (`uploadImage`), storing the result on the
  `truck_images` row, so saves aggregate flags without re-screening. Rekognition takes
  only JPEG/PNG bytes, so `ScreenImage` re-encodes the (WebP) source to JPEG first.
  A flagged upload **holds the truck immediately** (`uploadImage` calls the shared
  `holdForReview()` too, not just `save()`), so an offensive photo can never sit on an
  already-live truck until the vendor's next save. **Cuisine tags are the exception
  to hold-flagged-only:** a blocklisted tag name is **denied outright** at authoring
  (never created, a `newTagName` validation error — see *Profile & vendor
  management*, Cuisine tags) because tags are shared public taxonomy, not
  truck-scoped content an admin can hold. **Local testing without AWS:**
  when Rekognition is off, `ScreenImage` falls back to a filename stand-in — set
  `MODERATION_IMAGE_FILENAME_TRIGGERS` (e.g. `nsfw,explicit`) and any upload whose
  name contains a trigger is flagged (off unless set; ignored when Rekognition is on).
- **Admins are emailed when a truck is held.** On the **transition** into the held
  state (not every edit of an already-held truck), `save()` queues a
  `App\Mail\TruckHeldForReview` mailable to `config('admin.emails')`. It's
  `ShouldQueue`, so it never blocks or fails the vendor's save, and a no-op when the
  allowlist is empty.
- **The blocklist is env baseline + DB additions.** `config/moderation.php` holds a
  default word list (override via `MODERATION_TEXT_BLOCKLIST`) that is an
  **un-deletable floor**; `moderation_terms` rows (managed from the moderation page)
  are unioned on top. `ScreenText` reads the union, normalised (trim/lowercase/dedupe).
  `ModerationTerm::cachedTerms()` caches the DB terms and the model busts that cache
  on any save/delete, so an added or removed word takes effect on the next screen —
  **no redeploy**.
- **The queue (`/admin/trucks`).** Newest-first, flagged surfaced first. The
  `$filter` is `#[Url]`-bound (deep-linkable, e.g. `?filter=removed`). The
  "Needs review" tab is an **exception queue** — `is_published = false AND
  reviewed_at IS NULL`, i.e. only trucks that need a human decision: held
  (auto-flagged) trucks and restored trucks awaiting a fresh call. **A clean save
  publishes instantly, so clean trucks never enter the queue** (moderation is
  exception-driven, not a spot-check of every new truck). A "Removed" tab lists
  soft-deleted trucks; a
  "Reported" tab (with a count badge) lists **live trucks carrying open public
  reports**, most-reported first (see *Public reports* below); a
  "Reinstatement" tab (with a pending-count badge) lists open unban requests (see
  *Reinstatement requests* below). Actions:
  **approve** (`reviewed_at = now()`, publish the held truck, **and clear its images'
  flags** so a later vendor save doesn't re-hold it on the same approved photos),
  **remove** (soft-delete + reason — hidden everywhere via the `SoftDeletes` global
  scope, rows/images kept as evidence), **restore** (comes back unpublished +
  unreviewed), and **block vendor**. A **"Cuisine tags" panel** (chips with usage
  counts, mirroring the blocked-words panel) lets an admin **delete a tag** from
  the shared taxonomy — the cleanup for tags that slipped past the blocklist or
  predate an addition to it (authoring already denies blocklisted names). Hard
  delete behind a `wire:confirm`; the `food_truck_tag` FK cascade detaches it
  from every truck. **`remove()`/`blockOwner()` delegate to the shared
  `App\Actions\RemoveTruck` / `App\Actions\BlockVendor`** so the identical actions
  on the public truck detail page can't drift from the queue.
- **Moderator controls on the truck detail page.** People are creative, and some
  offensive content is only spotted live on the page — so `trucks/show` carries an
  **admin-only "Moderation" panel** (`@if (auth()->user()?->isAdmin())`, invisible to
  everyone else): **Remove this truck** and **Block this vendor**. The detail page is
  plain Blade (not Livewire), so these are **plain CSRF `POST` routes** in the `admin`
  group (`admin.trucks.remove` / `admin.trucks.block`, `whereNumber`), running the same
  guards and the same shared actions the queue uses. Each button is gated behind a
  **two-click Alpine confirm** (the profile delete-account reveal pattern) so a mis-tap
  can't fire. Both 404 the truck as a side effect, so each redirects to the queue:
  remove → the Removed tab (`?filter=removed`), block → review.
- **Public reports (`<x-report-truck>` → `POST /api/trucks/{truck}/report`).** A quiet
  megaphone in the truck detail-page footer lets **any visitor — signed-in or not —**
  flag a truck as offensive (one tap, no form; the `reportToggle` Alpine component in
  `resources/js/report.js` settles it into a "Reported" state; hidden for the truck's own
  owner). Reporting is **surface-only**: it writes a `truck_reports` row and lists the
  truck on the queue's Reported tab, but **never unpublishes it** — so a single click, or a
  pile-on, can't be a takedown lever. The endpoint is **guest-accessible** (not `auth`),
  throttled (`throttle:10,1`), published-trucks-only (404), and lives under `/api` (JSON
  errors). **Deduped per reporter** — by `user_id` when signed in, else by a hashed IP
  (`sha256`, mirroring `cookie_consents` — data minimisation) — so counts can't be
  inflated; a repeat click is an idempotent no-op. The admin acts from the Reported tab:
  **dismiss reports** (`dismissReports()` — a false alarm; marks the truck's open reports
  `dismissed`, truck stays live) or the existing **remove** / **block vendor** when the
  reports were justified (a removed truck keeps its report rows as evidence).
- **Blocking a vendor** (`App\Actions\BlockVendor`) stamps `users.banned_at`/`ban_reason`
  (set with `forceFill`, never mass-assignable) and unpublishes **all** their trucks at
  once. A ban stops `ProfilePage::addTruck()` and `TruckEditor::save()` only — the user
  can still sign in, browse, favourite, and **request reinstatement** from their profile.
  Their profile hides the "Add a food truck" CTA and shows the reinstatement form.
- **Reinstatement requests.** A blocked vendor can ask to be unblocked from their own
  profile: the old dead-end ban notice is now a **"Request reinstatement" form**
  (`ProfilePage::requestReinstatement()` — banned-only, one pending request at a time,
  optional message ≤ 1000 chars), which creates a `reinstatement_requests` row
  (`App\Models\ReinstatementRequest`). Admins review them on the queue's Reinstatement
  tab: **reinstate** (`reinstate()` — `forceFill` clears `banned_at`/`ban_reason`, marks
  the request approved) or **dismiss** (`dismissRequest()` — marks it dismissed, the ban
  stands; the vendor may submit a fresh request). **Reinstating lifts the ban only** —
  the vendor's previously-unpublished trucks stay down until they re-save each one (which
  re-runs the content screen), so nothing offensive silently comes back.
- **Nav:** an admins-only "Moderation" link appears in `<x-mobile-header>` (`$items`,
  gated by `auth()->user()?->isAdmin()`).

Env: `ADMIN_EMAILS`, `MODERATION_REKOGNITION_ENABLED` (+ optional
`MODERATION_REKOGNITION_MIN_CONFIDENCE`, `_REGION`, and `MODERATION_TEXT_BLOCKLIST`)
— see `.env.example`. Tests: `tests/Feature/Admin/ModerationQueueTest.php` (access
control, queue filters, approve/remove/restore/block, blocklist editing, tag
deletion), `tests/Feature/Admin/DetailPageModerationTest.php` (the detail-page
admin panel visibility + the remove/block POST routes and their 403 guards),
`tests/Feature/Admin/ReinstatementTest.php` (request → reinstate/dismiss lifecycle,
banned-only + one-pending guards),
`tests/Feature/Admin/ReportedQueueTest.php` (Reported tab listing/ordering, the
count badge, dismiss-reports, remove-from-reported),
`tests/Feature/Trucks/ReportTruckTest.php` (the report control + endpoint: guest
report, IP dedupe, signed-in attribution, published-only, owner-hidden), and
`tests/Feature/Moderation/ScreeningTest.php` (text/image flagging, ban guards) —
they set `config(['admin.emails' => …])` and mock the non-final `ScreenImage`.

## Social links

Vendors add social-media profile links to their truck; the detail page shows them
as a row of single-tone brand icons (`<x-social-links>`), and the truck page also
carries a **Get directions** CTA to Google Maps (see the `trucks.show` *Pages* entry).

- **The vendor pastes a URL; we detect the platform.** `App\Enums\SocialPlatform`
  (Facebook, Instagram, TikTok, X, YouTube, Snapchat, Website) has `fromUrl()`,
  which matches the link's host (exact domain **or any subdomain** — `m.facebook.com`,
  `www.tiktok.com`) against a per-platform domain list (Meta brands, `x.com`/
  `twitter.com`, `youtu.be`, …). **Anything unrecognised falls back to `Website`**
  (a generic globe) so an odd link still renders a working icon, never an error.
  `label()` gives the screen-reader/hover text.
- **Stored, not re-parsed.** `saveSocialLinks()` in `TruckEditor` writes the detected
  `platform` onto each `truck_social_links` row at save time, so the detail page just
  reads it (no host parsing on render). See *Profile & vendor management* and the
  `truck_social_links` schema row.
- **Icons are single-tone, token-coloured inline SVG** — brand glyphs as `fill`, the
  globe stroke-drawn — **not** brand hex, per the icon conventions
  (`resources/views/CLAUDE.md`). Hidden entirely when a truck has no links.
- Tests: `tests/Feature/Trucks/SocialPlatformTest.php` (host detection + fallback)
  and the social assertions in `tests/Feature/Trucks/TruckShowPageTest.php`.

## Support link (Buy Me a Coffee)

The app is free to use; a single **Buy Me a Coffee** link helps fund it, surfaced on
three surfaces and gated on one config value.

- **One env-backed URL.** `config/external-links.php` exposes
  `config('external-links.buymeacoffee')` from the `BUYMEACOFFEE_URL` env var (see
  `.env.example`). It's the single source of truth — the handle never lives in a view.
- **Config-gated everywhere.** Each surface renders **only when the value is truthy**,
  so a blank `BUYMEACOFFEE_URL=` hides all three cleanly (no empty card, no dead link):
  1. the **About page** — a "Support the project" entry appended to the `$connections`
     grid, reusing the existing `.about-page__card` pattern (no new CSS);
  2. the **profile page** — the `.profile__support` callout under the heading
     (`resources/css/components/profile.css`), a chili-barred card with a mustard
     coffee glyph, kept quieter than the "Add a food truck" CTA;
  3. the **`<x-mobile-header>` hamburger menu** — a `coffee` entry spread into
     `$items` (after "About us"). It carries an `external => true` flag that adds
     `target="_blank" rel="noopener noreferrer"`. It renders in the mobile menu
     **only** — the desktop top-nav loop `@continue`s past it (alongside `profile`)
     so it doesn't crowd the header. **Not** in the bottom `<x-mobile-nav>` either
     — that bar is for in-app navigation only.
- The coffee-cup icon is inline stroke SVG (viewBox `0 0 24 24`), matching the icon
  convention (see `resources/views/CLAUDE.md`).

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
DB_DATABASE=street_bites
DB_USERNAME=street_bites
DB_PASSWORD=street_bites
```

Default Laravel recipe credentials (`laravel/laravel`) are overridden.

The connection driver is **`mariadb`** (not `mysql`) to match the MariaDB 10.11
backend — Laravel's dedicated driver, set in `.env` and as the `config/database.php`
default.

### Test database

`phpunit.xml` points the suite at a separate **`street_bites_testing`** database on
the same MariaDB container (driver `mariadb`; host/user/password inherited from
`.env`). A `run_as_root` step on the `database` service in `.lando.yml` creates it
(idempotently) on every `lando start`, so a fresh clone needs no manual setup.

You never seed or sync data into it — DB-touching tests `use RefreshDatabase`,
which migrates the schema fresh and rolls back each test in a transaction. The
database only needs to *exist*; its contents are rebuilt automatically per run.

## Service URLs

| | URL |
|---|---|
| App | `https://street-bites.lndo.site` |
| Mailpit UI | `http://localhost:8025` (or `https://mailpit.street-bites.lndo.site`) |
| Reverb WebSocket (browser) | `ws://localhost:8080` |
| Vite dev server | `https://vite.street-bites.lndo.site:5173` |

## Running Vite

```bash
lando npm run dev
```

Run Vite in the `node` service. The browser loads dev assets from
**`https://vite.street-bites.lndo.site:5173`** — Vite serving **HTTPS directly**
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
   `node` entry in the `proxy:` block (`vite.street-bites.lndo.site:5173`), present
   **solely** to inject that hostname into the cert SANs. After changing it,
   `lando rebuild -s node` reissues the cert; verify with
   `lando ssh -s node -c "openssl x509 -in /certs/cert.crt -noout -ext subjectAltName"`.
3. **Published port, not the proxy.** The Lando proxy will **not** route to
   Vite's custom port (returns a Traefik 404), so `node` publishes `5173:5173`
   via `overrides.ports`. The browser hits `vite.street-bites.lndo.site` (which
   resolves to `127.0.0.1` via lndo.site DNS) on `:5173` directly. The `proxy:`
   entry from point 2 is **not** used for routing — only for the cert SAN — so
   the Traefik 404 never matters.
4. **`allowedHosts`.** Vite 8 returns `403 Blocked request` for non-allow-listed
   `Host` headers; `vite.config.js` allows `.lndo.site`.
5. **`origin`.** `server.origin` is `https://vite.street-bites.lndo.site:5173`, so
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

## Deployment (AWS)

Production deployment is entirely separate from Lando — Terraform and Docker
run directly on the host (or in CI), never through `lando`. Full detail is in
`docs_and_archetecture/deployment-infrastructure.md`; the cross-cutting facts
that matter while touching app code:

- **`Dockerfile` + `docker/`** build one production image; ECS runs it as
  three services (`web`, `reverb`, `queue-worker` — mirrors the `.lando.yml`
  split) that differ only in container command. `docker/nginx.conf` owns
  compression and browser caching (nothing upstream compresses — the ALB passes
  bytes through): gzip for text responses, `Cache-Control: immutable, 1 year`
  on `/build/` (safe because Vite fingerprints those filenames), a week on
  `/images/` and the favicons, plus `server_tokens off`. `docker/php.ini` (a
  conf.d drop-in) sets `expose_php = Off` **and the upload limits**
  (`upload_max_filesize = 6M`, `post_max_size = 10M`) — the base image ships no
  php.ini, and its compiled-in 2M default silently 302'd any 2–5 MB Livewire
  image upload in prod (Lando sets 100M, so it never reproduces locally). Both
  files are prod-only — Lando's `laravel` recipe doesn't build this image. The response security headers themselves (HSTS,
  CSP, …) are app-level, not nginx — see *Security headers*.
- **`config('filesystems.public_disk')`** (`config/filesystems.php`, env
  `FILESYSTEM_PUBLIC_DISK`) is what every truck-image/map-cache call site
  resolves through instead of a hardcoded `'public'` disk name — `public`
  (local disk) here in Lando, `s3` in production. Never hardcode `'public'` in
  new code that writes to the public disk; use the config key. The `s3` disk's
  `options` stamp `CacheControl: immutable, 1 year` on every object written —
  safe because all current write paths are content-addressed (uuid image names,
  fingerprinted map paths); keep new write paths content-unique or scope them
  their own options.
- **`terraform/`** — `bootstrap/` (applied once, by hand, never by CI) creates
  the state backend and the two GitHub OIDC IAM roles;
  `environments/prod/` is the actual infrastructure, built from
  `terraform/modules/*`. Adding a second environment later means copying
  `environments/prod/`, not restructuring the modules.
- **`.github/workflows/`** — `ci.yml` (PR quality gate), `terraform-plan.yml` /
  `terraform-apply.yml` (infra, gated behind the `production-infra`
  Environment), `release-deploy.yml` (builds + deploys on every published
  GitHub Release, migrating before rolling `web`).
- This repo's **GitHub default/integration branch is `develop`, not `main`** —
  PRs and day-to-day work target `develop`. Infra deploys are gated separately:
  `terraform-apply.yml` triggers on pushes to **`main`**, so a `terraform apply`
  only runs once `develop` is promoted into `main` (the OIDC trust policy in
  `terraform/bootstrap` is scoped to `main` via `var.github_main_branch`
  accordingly). `release-deploy.yml` (app deploys) is unaffected — it triggers
  on published GitHub Releases, not branch pushes.
- **Mail is Resend in production** (`MAIL_MAILER=resend` in `main.tf`'s
  `base_environment`; Mailpit is dev-only). Auth (sign-up verification + 2FA
  codes) depends on it. `street-bites.org` is verified as a sending domain in
  the **Resend dashboard** (its DKIM/SPF/MX records are managed by hand in
  Cloudflare from the values Resend issues — not in Terraform); sends as
  `noreply@street-bites.org`. The API key rides in as the `RESEND_API_KEY`
  ECS secret (Secrets Manager, `secrets.tf`) from the sensitive
  `resend_api_key` Terraform variable (tfvars locally, the `RESEND_API_KEY`
  repo secret via `TF_VAR_` in both terraform workflows — plan needs it too).
  **SES was removed deliberately** (AWS denied production access; decision:
  don't re-appeal) — don't reintroduce it. `config/services.php` already
  wires `RESEND_API_KEY` (note: not the Laravel docs' `RESEND_KEY`).
- **The production site is `https://www.street-bites.org`.** DNS is hosted at
  **Cloudflare** (records managed by Terraform via the `CLOUDFLARE_API_TOKEN`
  env var / CI secret, all DNS-only — no proxying); TLS terminates at the
  ALB (HTTPS:443, port 80 redirects) and at the Reverb NLB's TLS:443 listener
  (`ws.street-bites.org`, the browser's `VITE_REVERB_HOST` baked in by
  release-deploy.yml) with one ACM cert — see
  `terraform/environments/prod/domain.tf`. Because TLS ends at the load
  balancer, `bootstrap/app.php` trusts proxy `X-Forwarded-*` headers
  (`trustProxies`, AWS_ELB header set) — don't remove it, or generated URLs
  fall back to `http://`.
