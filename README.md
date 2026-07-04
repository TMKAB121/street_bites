# Steet Bites

A full-stack Laravel application built with Livewire, real-time WebSockets via Reverb, Redis-backed queues and sessions, and Tailwind CSS. Developed as part of a YouTube series — the project follows a structured build from initial scaffolding through a complete feature set.

All development runs inside Docker containers managed by [Lando](https://lando.dev/). No local PHP, Composer, or Node installation is required.

---

## Tech Stack

| Layer | Technology |
|---|---|
| Framework | Laravel 13 |
| Frontend Components | Livewire 4 |
| Client Interactivity | Alpine.js |
| WebSockets | Laravel Reverb 1 |
| Build Tool | Vite 8 |
| CSS | Tailwind CSS 4 |
| Database | MariaDB 10.11 |
| Maps | Leaflet + OpenStreetMap (keyless: tiles, static maps, Nominatim geocoding) |
| Cache / Queue / Sessions | Redis 7 |
| Runtime | PHP 8.3 |
| Node | 20.x |
| Testing | Pest 4 |
| PHP Quality | Pint · Larastan (PHPStan) · Rector |
| Asset Quality | ESLint · Stylelint · Prettier |
| Dev Environment | Lando (Docker) |

---

## Prerequisites

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) running
- [Lando](https://lando.dev/download/) installed

No local PHP, Composer, or Node needed — everything runs inside containers.

---

## First-Time Setup

```bash
# 1. Clone the repo
git clone <repo-url> steet_bites
cd steet_bites

# 2. Start Lando (boots all containers)
lando start

# 3. Run the one-shot setup script
#    Installs PHP deps, copies .env, generates app key,
#    runs migrations, installs JS deps, and builds assets
lando composer setup

# 4. Link the public storage disk (needed for food-truck image uploads)
lando artisan storage:link
```

---

## Running the Development Server

The app is served by nginx at https://steet-bites.lndo.site as soon as Lando is
up — there is no `artisan serve` step. Reverb also runs automatically as the
`reverb` service (don't start it manually — see the Reverb note below). Run the
remaining dev-time services individually, each in its own Lando command:

```bash
lando npm run dev      # Vite dev server with HMR (HTTPS on :5173 — see Vite note)
lando queue:work       # Redis queue worker
```

> Reverb is already running — `lando reverb:start` would collide on port 8080
> ("Address already in use"). Follow its output with `lando logs -s reverb -f`.

> Do **not** use `lando composer dev`. The stock Laravel `dev` script runs bare
> `vite` (no `--host`) and `artisan serve` inside the appserver container, which
> writes an unreachable `http://[::1]:5173` into `public/hot` and bypasses the
> nginx-served app. If assets ever 404 from a stale dev URL, delete `public/hot`
> to fall back to the built manifest in `public/build`.

---

## Service URLs

| Service | URL |
|---|---|
| App | https://steet-bites.lndo.site |
| Vite Dev Server | https://vite.steet-bites.lndo.site:5173 |
| Reverb WebSocket | ws://localhost:8080 |
| Mailpit (email UI) | http://localhost:8025 (or https://mailpit.steet-bites.lndo.site) |

---

## Lando Tooling Reference

```bash
lando artisan <cmd>    # Run any Artisan command
lando composer <cmd>   # Composer
lando npm <cmd>        # npm (Node 20 container)
lando pint             # Laravel Pint code style fixer
lando pest             # Pest test suite
lando logs -s reverb -f # Follow Reverb output (auto-runs as the reverb service)
lando queue:work       # Start Redis queue worker
lando mariadb          # Open a MariaDB shell
lando redis-cli        # Open a Redis shell
```

---

## Running Tests

Tests are written with [Pest](https://pestphp.com/) (running on top of PHPUnit).
The suite runs against a separate MariaDB database (`steet_bites_testing`) on the
same container, created automatically on `lando start` — no extra setup needed.
DB-touching tests `use RefreshDatabase` (migrate fresh, roll back per test).

```bash
lando pest                       # run the full suite
lando pest --filter=StyleGuide   # run a subset
lando composer test              # artisan test (also routes through Pest)
```

---

## Code Quality & Linting

A layered quality stack runs locally and on a pre-commit hook. See
[`docs_and_archetecture/linting-and-code-quality.md`](../docs_and_archetecture/linting-and-code-quality.md)
for full detail.

| Concern | Tool | Command |
|---|---|---|
| PHP style | Laravel Pint | `lando pint` (fix) · `lando pint --test` (check) |
| PHP static analysis | Larastan / PHPStan (level 8) | `lando composer stan` |
| PHP refactoring | Rector | `lando composer rector:dry` · `lando composer rector` |
| CSS lint | Stylelint | `lando npm run lint:css` (`:fix` to auto-fix) |
| JS lint | ESLint | `lando npm run lint:js` (`:fix` to auto-fix) |
| JS/JSON format | Prettier | `lando npm run format` · `lando npm run format:check` |
| All JS checks | — | `lando npm run lint` |
| All PHP checks | — | `lando composer lint` |

### Pre-commit hook

`.githooks/pre-commit` runs Pint, Larastan, ESLint, Stylelint, and Prettier on
every commit — a failure aborts the commit. It is enabled automatically by
`lando composer setup` (`git config core.hooksPath .githooks`). To enable it
manually in an existing clone:

```bash
git config core.hooksPath .githooks
```

Bypass in an emergency with `git commit --no-verify`. Rector is **not** in the
hook — run it on demand and review its diff before committing.

---

## Project Structure

```
steet_bites/
├── app/
│   ├── Actions/                # single-purpose actions (StoreTruckImage,
│   │                           #   GenerateTruckMapImage, ReverseGeocodeLabel, GeocodeSearch)
│   ├── Http/
│   │   ├── Controllers/
│   │   └── Middleware/         # RequireCookieConsent (cookie-consent gate on auth/profile)
│   ├── Livewire/               # Livewire components (Auth/, Profile/)
│   ├── Models/                 # User, FoodTruck, MenuItem, TruckImage, ...
│   ├── Jobs/                   # Queued jobs
│   └── Events/                 # Broadcast events
├── database/
│   ├── migrations/
│   ├── factories/
│   └── seeders/
├── public/
│   ├── favicon.svg             # vector icon (pin mark) + favicon.ico / apple-touch-icon.png
│   └── images/                 # brand assets: street-bites-logo.svg (header logo),
│                               #   transparent logo/icon PNGs
├── resources/
│   ├── css/                    # Tailwind 4 CSS-first design system
│   │   ├── app.css             # entry: @import 'tailwindcss' + partials
│   │   ├── theme.css           # @theme design tokens (source of truth)
│   │   ├── base.css            # global base + ADA accessibility rules
│   │   └── components/         # .btn, .food-truck-card, .mobile-nav, .mobile-header,
│   │                           #   .card-carousel, .filter-row, .profile, .truck-form, .toast, ...
│   ├── js/
│   │   ├── app.js              # imports the JS modules below (Alpine is bundled by Livewire 4)
│   │   ├── echo.js             # Laravel Echo / Reverb client config
│   │   ├── truck-map.js        # Leaflet home-page map + closest-first card sorting
│   │   │                       #   + 100-mile radius cap + ZIP/address location-search fallback
│   │   ├── favorites.js        # favourite star toggle (optimistic Alpine + fetch)
│   │   └── search.js           # header search typeahead dropdown
│   └── views/
│       ├── components/         # anonymous Blade components (x-mobile-nav, x-mobile-header,
│       │                       #   x-food-truck-card, x-favorite-toggle, x-truck-discovery,
│       │                       #   x-card-carousel, x-truck-filters, x-truck-form, x-truck-map,
│       │                       #   x-location-search, x-toast, x-cookie-consent)
│       ├── layouts/            # app.blade.php (centered) + shell.blade.php (mobile chrome)
│       ├── livewire/           # full-page Livewire views (auth/, profile/)
│       ├── trucks/show.blade.php # public truck detail page (/trucks/{id})
│       ├── welcome.blade.php   # home page — assembled mobile shell (/)
│       ├── favorites.blade.php # signed-in favorites page (/favorites)
│       ├── search.blade.php    # search landing page (/search?q=…)
│       ├── about.blade.php     # public "About us" page (/about)
│       └── styleguide.blade.php # living style guide (/styleguide)
├── routes/
│   ├── web.php
│   └── channels.php            # Reverb broadcast channel definitions
├── config/
│   ├── broadcasting.php        # Reverb configured as default broadcaster
│   └── reverb.php
├── tests/                      # Pest tests (Feature + Unit)
├── .github/workflows/          # CI + Terraform plan/apply + release-deploy (see Deployment)
├── docker/                     # nginx/php-fpm/supervisord config for the production image
├── terraform/                  # AWS infrastructure (bootstrap/, modules/, environments/prod/)
├── Dockerfile                  # production image (see Deployment)
├── .githooks/pre-commit        # quality gate (Pint, Larastan, ESLint, ...)
├── .stylelintrc.json           # Stylelint config (Tailwind-aware)
├── eslint.config.js            # ESLint flat config
├── .prettierrc.json            # Prettier config
├── phpstan.neon                # Larastan / PHPStan config (level 8)
├── rector.php                  # Rector config
├── .lando.yml                  # Lando (Docker) service definitions
└── vite.config.js
```

---

## Front-End & Design System

Styling uses **Tailwind CSS 4**, configured CSS-first (no `tailwind.config.js`).
The "Urban Vibrant" design system is encoded as Tailwind `@theme` tokens in
`resources/css/theme.css` — each token generates both a CSS variable and utility
classes from a single source of truth. A living style guide renders at
[`/styleguide`](https://steet-bites.lndo.site/styleguide).

Branding is vector-first: the Street Bites logo (map pin + wordmark) ships as an
SVG in `public/images/` and renders in the app header, and every page links the
favicon set (`favicon.svg` with `.ico` and apple-touch fallbacks) from `public/`.

Reusable UI is built as **anonymous Blade components** in
`resources/views/components/` (mobile header, bottom nav, food-truck card, the
favourite star, card carousel, cuisine filters, the shared discovery section, the
vendor truck form, a toast, and the cookie-consent banner), each pairing a CSS
partial with a Blade template. The home page (`/`) assembles them into a mobile app
shell. Client-side interactivity (e.g. the header's hamburger menu and the toast)
is powered by **Alpine.js**, which **Livewire 4 bundles and starts automatically** —
do not start a second Alpine instance in `resources/js/app.js`. Carousels and the
filter row use native **CSS scroll-snap** rather than a JS slider library.

Vite compiles both CSS and JS:

```bash
lando npm run build   # production build (minified, hashed)
lando npm run dev     # dev server with HMR
```

The dev server serves **HTTPS** at `https://vite.steet-bites.lndo.site:5173`
(directly on a published host port — the Lando proxy won't route Vite's custom
port). HTTPS is required because the app is HTTPS and browsers block mixed
content; the `node` service's `ssl: true` provides a CA-trusted cert at `/certs`
that `vite.config.js` loads. This is all pre-wired — just run the command. If
assets 404 from a stale URL, delete `public/hot` to fall back to `public/build`.

See [`docs_and_archetecture/frontend-framework.md`](../docs_and_archetecture/frontend-framework.md)
for the front-end framework, and
[`docs_and_archetecture/ui-component-architecture.md`](../docs_and_archetecture/ui-component-architecture.md)
for the component library and its design decisions.

---

## Cookie Consent (GDPR)

A custom consent banner appears on first visit. The app sets **only essential
cookies** (session-backed sign-in and favorites — no analytics, ads, or
tracking), so consent is deliberately **all-or-nothing**: accept, or decline and
keep browsing anonymously without accounts/favorites.

- **No dark patterns** — Accept and Decline are rendered with identical size,
  color, and font (one shared CSS class enforces the equal prominence GDPR
  requires), and nothing is pre-selected.
- **Easy withdrawal** — a persistent round "cookie preferences" button stays on
  every page and reopens the banner; withdrawing consent while signed in also
  signs the user out.
- **Documented consent** — every accept/decline is logged to a `cookie_consents`
  audit table (policy version, hashed IP, user agent, user id when signed in).
- **Enforced server-side** — the `RequireCookieConsent` middleware gates the
  sign-in/sign-up flows and `/profile`; without consent those routes redirect
  home, where the banner reopens and explains.

The choice is stored in an encrypted cookie for ~6 months, after which the
banner re-prompts. See `CLAUDE.md` → *Cookie consent (GDPR)* for conventions.

---

## Authentication

Two email-verified [Livewire](https://livewire.laravel.com/) flows live under
`app/Livewire/Auth/`:

- **Sign-up** — enter email → confirm a 6-digit code (or click the emailed magic
  link) → set a password. The account is created only after the email is verified.
- **Sign-in** — email + password (primary factor) → a one-time code emailed as a
  second factor → home. The bottom nav and hamburger menu show a **Login** link
  for guests and **Profile** once authenticated.

Security follows NIST SP 800-63B / OWASP guidance:

- **Argon2id** password hashing (`config/hashing.php`), with legacy bcrypt hashes
  rehashed on next sign-in.
- Password policy of **min 12 characters** plus a **breach-database check** (Have
  I Been Pwned) — favouring length over forced complexity.
- Hardened session cookies (`Secure`, `HttpOnly`, `SameSite`, encrypted payload);
  the session id is regenerated on login.
- Generic, rate-limited errors at each step to resist account enumeration and
  brute force.

> The second factor is **email OTP** by design (not TOTP/SMS) to limit PII and
> complexity for now. See `CLAUDE.md` → *Authentication* for the full convention set.

---

## Profile & Vendor Management

`/profile` (the **Profile** link in the nav/menu once signed in) is the
authenticated home for two roles in one page:

- **Eater by default** — it shows the trucks a user has favourited. No one is
  assumed to be a vendor.
- **Vendor on demand** — an "Add a food truck" button registers a food truck
  against the user. Owned trucks list as collapsible cards; expanding one
  **lazy-loads** an editable form for the truck's name, **today's** operating
  hours, menu items, photos, and **live location**. Saving a new truck publishes
  it. Confirmations surface as toasts.
- **Real-time presence** — a **Set my location** button pins the truck at the
  vendor's current GPS position (and reverse-geocodes an area label); a **Now Open**
  button stamps today's opening time. Both capture the browser timezone so times show
  in the truck's local zone (the app runs in UTC).

Data is persisted in a normalized schema (`food_trucks`, `truck_operating_hours`,
`menu_items`, `truck_images`, and a `favorites` pivot). Photo uploads are
normalized to a **250×250 WebP** (centre-cropped, EXIF stripped) via
[`intervention/image`](https://image.intervention.io/) and stored on the public
disk — so a fresh environment needs `lando artisan storage:link` once (see setup).
See `CLAUDE.md` → *Profile & vendor management* for the schema and conventions.

---

## Discovery & Maps

Each published truck has a **detail page** (`/trucks/{id}`) that discovery cards
link to — its photos, cuisine tags, today's hours ("Open now — since …" when a
vendor has flipped **Now Open**), location, menu, and a map of the surrounding area.
Trucks that are **serving right now** carry a red **"Now Open"** badge on their card
and are listed **first** — the home page leads with open trucks (alphabetical), and once
location is shared the cards re-sort to the **nearest open truck first**. The home page
also shows an **interactive map** of pinned trucks that centres on the visitor's location,
with pins that filter alongside the cuisine pills. Visitors who **decline the GPS
prompt** get a fallback instead: a search card appears where they can enter a **ZIP
code or address**, which is geocoded to rough coordinates — the map recenters and
the cards re-sort just as if location had been shared.

Once a location is known (either way), every result surface applies a **100-mile
radius cap**: trucks further out are hidden from the discovery grids, the Popular
carousel, the search results, and the map's pins, so visitors only ever see trucks
they could realistically reach. The location is remembered for the browser session,
so it keeps working across pages without re-prompting.

All mapping uses **OpenStreetMap** with **no API key or billing**: truck pages
render a cached static map ([`dantsu/php-osm-static-api`](https://github.com/DantSu/php-osm-static-api)),
the home page uses [Leaflet](https://leafletjs.com/), and both geocoding directions
come from OSM **Nominatim** — reverse (pin → area label) and forward (typed
ZIP/address → coordinates, proxied through a cached, rate-limited `/api/geocode`
endpoint). See `CLAUDE.md` → *Maps & geolocation*.

---

## Favorites & Search

Signed-in eaters can **star any truck** — on its discovery card, on its detail
page, or from the favourites list on `/profile`. The star toggles instantly
(optimistic UI backed by a `POST /api/favorites/{truck}` endpoint) and drives
three things: the **Popular near you** carousel on the home page (the ten
most-favourited trucks, open-now first), the filled stars across discovery, and
the **`/favorites` page** — the home page's full discovery section (cuisine
filters, map, distance-sorted cards) scoped to the trucks you've starred.
Guests see no stars; favorites are part of the essential-cookie account
features behind cookie consent.

The **header search bar** works on every page: type two or more characters and
a dropdown suggests matching trucks — matched by **truck name, cuisine tag
(e.g. "Burgers"), or menu item name** — each linking straight to its truck
page, with a hint of *why* it matched when the name alone doesn't show it.
Pressing **Enter** (or tapping the magnifier) lands on `/search`, which lists
every matching truck as discovery cards, open-now trucks first. See
`CLAUDE.md` → *Favorites* and *Search*.

---

## Environment & Configuration Notes

### Database Credentials

```
DB_DATABASE=steet_bites
DB_USERNAME=steet_bites
DB_PASSWORD=steet_bites
```

### Reverb Host Split

Two separate env variables control Reverb — **do not collapse them into one**:

| Variable | Value | Used by |
|---|---|---|
| `REVERB_HOST` | `reverb` | PHP on the server (Docker internal network) |
| `VITE_REVERB_HOST` | `localhost` | Browser (reaches Reverb on `localhost:8080`) |

Docker service names are not resolvable from the browser, so these must stay separate.

The browser's `localhost:8080` works because the `reverb` service in `.lando.yml`
binds host port 8080 with an **explicit `ports: ['8080:8080']`** mapping. Do not
swap this for Lando's `portforward:` directive — for this custom service it
assigns a *random* host port and silently breaks `localhost:8080`. Reverb itself
runs automatically as that service's main process; never run `lando reverb:start`
manually (it collides on 8080).

### Internal Docker Hostnames

Services communicate by Docker service name, not `localhost`:

| Service | Internal Hostname | External (Host Machine) |
|---|---|---|
| MariaDB | `database` | `127.0.0.1:3306` |
| Redis | `cache` | `127.0.0.1:6379` |
| Reverb | `reverb` | `localhost:8080` |

---

## Deployment (AWS)

Production runs on **ECS Fargate** — three services from one Docker image
(`web`, `reverb`, `queue-worker`), RDS MariaDB, ElastiCache Redis, and S3 for
truck images/map caches. Infrastructure is defined in `terraform/` and rolled
out via GitHub Actions (`.github/workflows/`): a PR quality gate, Terraform
plan/apply on infra changes, and a release-triggered build-and-deploy
pipeline. This is entirely separate from local dev (no Lando involved).

See [`terraform/bootstrap/README.md`](terraform/bootstrap/README.md) for
one-time AWS/GitHub setup,
[`terraform/environments/prod/README.md`](terraform/environments/prod/README.md)
for provisioning the environment, and
[`docs_and_archetecture/deployment-infrastructure.md`](../docs_and_archetecture/deployment-infrastructure.md)
for the full architecture writeup.

---

## YouTube Series

This project is built live across a YouTube series. Each commit maps to a video episode — follow along to see every decision made from scratch.

https://www.youtube.com/playlist?list=PLCFAvrjCdis-mdDgzj3wAYA6wXjzgml9z

The app itself tells this story on its public **About page** (`/about`, linked from
the menu) — the mission, the developer, and follow-along links to the YouTube
series, the GitHub repo, and LinkedIn.
