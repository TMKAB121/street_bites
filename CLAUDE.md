# steet-bites

Laravel 13 · Livewire 4 · Reverb 1 · MariaDB 10.11 · Redis 7 · Vite/Node 20

Local dev runs entirely inside Lando (Docker). Do not assume local PHP, Composer, or Node. Prefix all runtime commands with `lando`.

## Lando tooling

```bash
lando artisan <cmd>
lando composer <cmd>
lando npm <cmd>
lando reverb:start        # starts the Reverb WebSocket server in the foreground
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

CSS is split into partials under `resources/css/`, all imported by `app.css`:

```
resources/css/
├── app.css              # entry — @import 'tailwindcss' + the partials below
├── theme.css            # @theme: design tokens (colors, type scale, radius, a11y)
├── base.css             # body, ADA tap targets, focus-visible ring
└── components/
    ├── button.css       # .btn / .btn-primary / .btn-accent / .btn-mustard
    └── card.css         # .food-truck-card
```

- Tokens are the source of truth — edit `theme.css`, never hard-code hex values
  in components. `--color-accent-chili` auto-generates `bg-accent-chili`,
  `text-accent-chili`, etc.
- Mustard (`--color-accent-mustard`) must use **dark text only** (fails contrast
  with white); `.btn-mustard` enforces this.
- A living style guide renders at `/styleguide` (route in `routes/web.php`).
- Vite compiles both CSS and JS (`resources/js/app.js`). Build: `lando npm run
  build`. Dev: `lando npm run dev -- --host 0.0.0.0`.

## Internal Docker hostnames

Services communicate by Docker service name, not `localhost`. Use these in config and `.env`:

| Service | Internal hostname | External (host machine) |
|---|---|---|
| MariaDB | `database` | `127.0.0.1:3306` |
| Redis | `cache` | `127.0.0.1:6379` |
| Reverb WebSocket | `reverb` | `localhost:8080` |

## Reverb host split — important

`REVERB_HOST=reverb` in `.env` is the **server-side** hostname — PHP in `appserver` uses `config/broadcasting.php → options.host` to connect to the Reverb container over the internal Docker network.

`VITE_REVERB_HOST=localhost` is hardcoded separately — the browser reaches Reverb via the Lando portforward on `localhost:8080`. It cannot inherit `REVERB_HOST` because Docker service names are not resolvable from the browser.

Do not collapse these into one variable.

## Database credentials

```
DB_DATABASE=steet_bites
DB_USERNAME=steet_bites
DB_PASSWORD=steet_bites
```

Default Laravel recipe credentials (`laravel/laravel`) are overridden.

## Service URLs

| | URL |
|---|---|
| App | `https://steet-bites.lndo.site` |
| Mailpit UI | `https://mailpit.steet-bites.lndo.site` |
| Reverb WebSocket (browser) | `ws://localhost:8080` |
| Vite dev server | `http://localhost:5173` |

## Running Vite

Must pass `--host 0.0.0.0` for the dev server to be reachable from outside the container:

```bash
lando npm run dev -- --host 0.0.0.0
```

## Reverb container startup

The `reverb` Lando service polls for `/app/artisan` before starting `artisan reverb:start`. It auto-recovers once the Laravel install exists — no manual restart needed after `lando composer install`.
