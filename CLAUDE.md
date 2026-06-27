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
`docs_and_archetecture/linting-and-code-quality.md` (on the Desktop, outside the
repo). Quick reference:

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
    └── filters.css      # .filter-row / .filter-pill
```

- Tokens are the source of truth — edit `theme.css`, never hard-code hex values
  in components. `--color-accent-chili` auto-generates `bg-accent-chili`,
  `text-accent-chili`, etc.
- Mustard (`--color-accent-mustard`) must use **dark text only** (fails contrast
  with white); `.btn-mustard` enforces this. On a *dark* surface (e.g. the
  bottom nav, the header menu) mustard text is fine — the rule is white-bg only.
- Vite compiles both CSS and JS (`resources/js/app.js`). Build: `lando npm run
  build`. Dev: `lando npm run dev` (HTTPS dev-server settings live in
  `vite.config.js`; see "Running Vite" below).

### Blade components

Reusable UI lives in `resources/views/components/` as **anonymous Blade
components**. Each pairs a CSS partial (visuals, BEM, `@layer components`) with a
`.blade.php` file (markup + `@props`):

| Component | CSS partial | Notes |
|---|---|---|
| `<x-mobile-nav>` | `nav.css` | Bottom tab bar (Home/Map/Favorites/Profile) |
| `<x-mobile-header>` | `header.css` | Top bar: hamburger + brand + map + search; hamburger opens a full-screen Alpine menu |
| `<x-food-truck-card>` | `card.css` | Image + title + Mustard FIND NOW CTA; `image` prop, graceful placeholder when null |
| `<x-card-carousel>` | `carousel.css` | Slot-based horizontal scroller; any child card becomes a snap item |
| `<x-truck-filters>` | `filters.css` | Scrollable cuisine pills; Alpine-driven active state (visual only — real filtering becomes Livewire later) |

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

`resources/js/app.js` imports `echo.js` (Reverb) **and starts Alpine.js**
(`window.Alpine`). Alpine is the client-side primitive for interactive components
(e.g. the header menu toggle via `x-data`/`x-show`); `[x-cloak]` is globally
hidden in `base.css` so collapsed UI never flashes on load. Prefer Alpine for
pure-UI state; reserve Livewire for server-backed interactivity.

### Pages

- `/` → `welcome.blade.php` — the **assembled mobile shell**: `<x-mobile-header>`,
  a `<x-card-carousel>`, `<x-truck-filters>` + a results grid, and `<x-mobile-nav>`.
  Content uses `pt-32 pb-24 md:pt-8 md:pb-8` to clear the fixed bars on mobile.
- `/styleguide` → `styleguide.blade.php` — living style guide demoing every token
  and component in isolation (route in `routes/web.php`).

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
| Mailpit UI | `https://mailpit.steet-bites.lndo.site` |
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
   which makes Lando issue a CA-trusted `*.lndo.site` cert at `/certs`.
   `vite.config.js` reads `/certs/cert.crt`+`cert.key` into `server.https`.
2. **Published port, not the proxy.** The Lando proxy will **not** route to
   Vite's custom port (returns a Traefik 404), so `node` publishes `5173:5173`
   via `overrides.ports`. The browser hits `vite.steet-bites.lndo.site` (which
   resolves to `127.0.0.1` via lndo.site DNS) on `:5173` directly; the cert
   covers that hostname, so it's valid TLS with no warning.
3. **`allowedHosts`.** Vite 8 returns `403 Blocked request` for non-allow-listed
   `Host` headers; `vite.config.js` allows `.lndo.site`.
4. **`origin`.** `server.origin` is `https://vite.steet-bites.lndo.site:5173`, so
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
