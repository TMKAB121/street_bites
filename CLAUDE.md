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
