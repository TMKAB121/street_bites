# steet-bites

Laravel 13 · Livewire 4 · Reverb 1 · MariaDB 10.11 · Redis 7 · Vite/Node 20

Local dev runs entirely inside Lando (Docker). Do not assume local PHP, Composer, or Node. Prefix all runtime commands with `lando`.

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
`tailwind.config.js`). The "Urban Vibrant" design system from the style guide is
encoded as Tailwind `@theme` tokens, which generate both CSS variables and
utility classes from one source of truth. Full detail lives in
`docs_and_archetecture/frontend-framework.md`.

CSS is split into partials under `resources/css/`, all imported by `app.css`
(order matters: tokens → base → components):

```
resources/css/
├── app.css              # entry — @import 'tailwindcss' + the partials below
├── theme.css            # @theme: design tokens (colors, type scale, radius, a11y)
├── base.css             # body, ADA tap targets, focus-visible ring, [x-cloak]
└── components/
    ├── button.css       # .btn / .btn-primary / .btn-accent / .btn-mustard
    ├── card.css         # .food-truck-card (image + title + FIND NOW CTA)
    ├── nav.css          # .mobile-nav (bottom tab bar)
    ├── header.css       # .mobile-header + .mobile-search + .mobile-menu
    ├── carousel.css     # .card-carousel (CSS scroll-snap)
    ├── filters.css      # .filter-row / .filter-pill
    ├── auth.css         # .auth-card + .field (login / sign-up form styling)
    ├── profile.css      # .profile + .truck-disclosure (collapsible owned-truck cards)
    ├── truck-form.css   # .truck-form + .menu-row + .truck-image (vendor edit form)
    ├── tag-picker.css   # .tag-picker + .tag-pill (cuisine tag toggles in truck editor)
    └── toast.css        # .toast / .toast-stack (transient save/upload confirmations)
```

- Tokens are the source of truth — edit `theme.css`, never hard-code hex values
  in components. `--color-accent-chili` auto-generates `bg-accent-chili`,
  `text-accent-chili`, etc.
- Mustard (`--color-accent-mustard`) and Tangerine (`--color-accent-tangerine`)
  must use **dark text only** (both fail contrast with white). On a *dark* surface
  (e.g. the bottom nav, the header menu) mustard text is fine — the rule is
  white-bg only. Tangerine is currently used for the active filter pill.
- Vite compiles both CSS and JS (`resources/js/app.js`). Build: `lando npm run
  build`. Dev: `lando npm run dev` (HTTPS dev-server settings live in
  `vite.config.js`; see "Running Vite" below).

### Blade components

Reusable UI lives in `resources/views/components/` as **anonymous Blade
components**. Each pairs a CSS partial (visuals, BEM, `@layer components`) with a
`.blade.php` file (markup + `@props`):

| Component | CSS partial | Notes |
|---|---|---|
| `<x-mobile-nav>` | `nav.css` | Bottom tab bar (Home/Map/Favorites + auth-aware slot: **Login** when guest, **Profile** when signed in) |
| `<x-mobile-header>` | `header.css` | Top bar: hamburger + brand + map + search; hamburger opens a full-screen Alpine menu (last link is the same auth-aware Login/Profile slot) |
| `<x-food-truck-card>` | `card.css` | Image + title + Mustard FIND NOW CTA; `image` prop, graceful placeholder when null |
| `<x-card-carousel>` | `carousel.css` | Slot-based horizontal scroller; any child card becomes a snap item |
| `<x-truck-filters>` | `filters.css` | Scrollable cuisine pills driven from the `Tag` DB. Each pill click sets Alpine's local active state **and** dispatches a `tag-filter` window event (`{ tag: slug }`). The results grid listens with `@tag-filter.window` and uses `x-show` to filter cards client-side. Props: `tags` (Collection of Tag models) |
| `<x-truck-form>` | `truck-form.css` | The vendor edit form. **Nested inside the `TruckEditor` Livewire view** (not a standalone demo): it compiles inline, so its `wire:model` / `wire:click` bind to the component. Props: `truck-id`, `menu-items`, `images`, `all-tags` |
| `<x-toast>` | `toast.css` | App-wide transient confirmations. Alpine-only; listens for the browser `toast` event Livewire dispatches (`$this->dispatch('toast', message:…, type:…)`). Stacked once in `layouts/shell.blade.php` |

Conventions for these components:

- **Mobile-only:** the header/nav apply `md:hidden`; desktop variants are
  deferred. Positioning utilities (`fixed`, `top-0`/`bottom-0`, `md:hidden`) live
  on the component element via `$attributes->class([...])`, **not** in the CSS
  partial — the partial owns visuals only. A `fixed` prop (default `true`) toggles
  in-flow rendering for styleguide demos.
- **Icons are inline SVG** (no icon library) — store path data in a `@php $icons`
  array and inject with `{!! … !!}`; styling comes from CSS (`stroke: currentcolor`).
- **Carousels & the filter row use native CSS scroll-snap** (`overflow-x` +
  `scroll-snap-type`), not a JS slider lib — Livewire-safe and ADA-native. The
  scroll region is `tabindex="0"` + `role="region"` for keyboard scrolling.

### JavaScript / Alpine

`resources/js/app.js` imports `echo.js` (Reverb) only. **Do not import or start
Alpine here.** Livewire 4 bundles its own Alpine and starts it automatically;
running a second instance (the standalone `alpinejs` package) triggers a
"multiple instances of Alpine" conflict that silently breaks every `wire:`
directive. Livewire's bundled Alpine scans the whole document, so plain
`x-data`/`x-show` markup still works, and it's exposed on `window.Alpine` for any
custom directives. `[x-cloak]` is globally hidden in `base.css` so collapsed UI
never flashes on load. Prefer Alpine for pure-UI state; reserve Livewire for
server-backed interactivity.

Because Livewire owns the JS, every full-page view must include `@livewireStyles`
in `<head>` and `@livewireScripts` before `</body>` — present in `welcome`,
`styleguide`, and both layouts (`layouts/app.blade.php`, `layouts/shell.blade.php`).

### Pages

- `/` → `welcome.blade.php` — the **assembled mobile shell**: `<x-mobile-header>`,
  a `<x-card-carousel>`, `<x-truck-filters>` + a results grid, and `<x-mobile-nav>`.
  Content uses `pt-32 pb-24 md:pt-8 md:pb-8` to clear the fixed bars on mobile.
  The route closure queries published `FoodTruck`s (with images + tags eager-loaded)
  and all `Tag`s that have at least one published truck, passing both to the view.
  Client-side tag filtering is Alpine-driven via the `tag-filter` window event.
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
  and the code step gives a generic "invalid or expired" error. Both steps are
  rate-limited via `RateLimiter`. Pending sign-in is tracked by
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

## Profile & vendor management

`/profile` (`App\Livewire\Profile\ProfilePage`, `auth`-guarded) is the signed-in
home for two roles in one page. Views live in `resources/views/livewire/profile/`.

- **Eater by default, vendor on demand.** We never assume a user is a food-truck
  owner: the page shows their **favourited trucks**, and only an explicit "Add a
  food truck" CTA (`addTruck()`) creates a `FoodTruck` tied to their user id. A
  user can own several.
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
- **Testing lazy components:** pass `['truckId' => …, 'lazy' => false]` to
  `Livewire::test()` so `mount()` runs immediately (see `tests/Feature/Profile/`).

### Normalized schema

Six migrations (`2026_06_29_0000xx_*` + `2026_06_30_000001_*`), all `cascadeOnDelete` from the truck:

| Table | Shape / decisions |
|---|---|
| `food_trucks` | `user_id` owner, `name`, `description`; nullable `latitude`/`longitude`/`location_label`/`located_at` (**geolocation columns are reserved — no pin-setting UI yet**); `is_published` gates discovery |
| `truck_operating_hours` | One row **per business date** (`unique(food_truck_id, business_date)`) — vendors operate in real time day-by-day, **not** on a recurring weekly schedule. The editor only upserts **today's** row via `updateOrCreate` |
| `truck_images` | `path` to a normalized WebP on the public disk + `sort_order` |
| `menu_items` | `name`, `description`, `price_cents` (**money as integer cents, never float**), `is_available`, `sort_order` |
| `favorites` | `user_id`+`food_truck_id` pivot (`unique`). Reads for display; the favourite/unfavourite action is a follow-up |
| `tags` + `food_truck_tag` | Cuisine taxonomy. `tags`: `name`, `slug` (unique, auto-generated from name via `Str::slug()` on creating). `food_truck_tag`: composite PK pivot — no timestamps, cascade deletes on both FKs |

Models: `FoodTruck` (`user`, `operatingHours`, `todayHours`, `images`,
`menuItems`, `favoritedBy`, `tags`), `TruckOperatingHour`, `TruckImage` (`url` accessor),
`MenuItem` (`price` accessor), `Tag` (`foodTrucks`); `User` gained `foodTrucks()` and `favorites()`.

### Image pipeline

Uploads go through `App\Actions\StoreTruckImage` (uses **`intervention/image` v4**,
GD/Imagick both available in the container): `cover(250, 250)` (centre-crop to a
1:1 square) → `WebpEncoder(quality: 80)` → stored at
`truck-images/{truck}/{uuid}.webp` on the **public** disk. Re-encoding strips
EXIF/GPS metadata (privacy) and arbitrary file bytes; the component validates
`image|mimes:jpeg,png,webp|max:5120`.

> Image uploads need the public-disk symlink — run `lando artisan storage:link`
> once per environment (a fresh clone has no `public/storage`).

### Dev seed

`database/seeders/FoodTruckSeeder` (called by `DatabaseSeeder`) creates 3 vendor
users, the full tag taxonomy, and 10 published trucks with menu items and images.
Fixture images live in `database/seeders/fixtures/images/` and are processed
through `StoreTruckImage` (same pipeline as live uploads). Re-seed with:

```bash
lando artisan migrate:fresh --seed
```

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
