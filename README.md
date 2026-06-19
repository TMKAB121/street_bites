# Steet Bites

A full-stack Laravel application built with Livewire, real-time WebSockets via Reverb, Redis-backed queues and sessions, and Tailwind CSS. Developed as part of a YouTube series — the project follows a structured build from initial scaffolding through a complete feature set.

All development runs inside Docker containers managed by [Lando](https://lando.dev/). No local PHP, Composer, or Node installation is required.

---

## Tech Stack

| Layer | Technology |
|---|---|
| Framework | Laravel 13 |
| Frontend Components | Livewire 4 |
| WebSockets | Laravel Reverb 1 |
| Build Tool | Vite 8 |
| CSS | Tailwind CSS 4 |
| Database | MariaDB 10.11 |
| Cache / Queue / Sessions | Redis 7 |
| Runtime | PHP 8.3 |
| Node | 20.x |
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
```

---

## Running the Development Server

```bash
# Start everything at once (PHP server, queue worker, log monitor, Vite HMR)
lando composer dev

# Or run each piece individually:
lando npm run dev -- --host 0.0.0.0   # Vite dev server with HMR
lando reverb:start                      # Reverb WebSocket server
lando queue:work                        # Redis queue worker
```

---

## Service URLs

| Service | URL |
|---|---|
| App | https://steet-bites.lndo.site |
| Vite Dev Server | http://localhost:5173 |
| Reverb WebSocket | ws://localhost:8080 |
| Mailpit (email UI) | https://mailpit.steet-bites.lndo.site |

---

## Lando Tooling Reference

```bash
lando artisan <cmd>    # Run any Artisan command
lando composer <cmd>   # Composer
lando npm <cmd>        # npm (Node 20 container)
lando pint             # Laravel Pint code style fixer
lando reverb:start     # Start Reverb WebSocket server
lando queue:work       # Start Redis queue worker
lando mariadb          # Open a MariaDB shell
lando redis-cli        # Open a Redis shell
```

---

## Running Tests

```bash
lando composer test
```

PHPUnit is configured with an in-memory SQLite database — no extra setup needed.

---

## Project Structure

```
steet_bites/
├── app/
│   ├── Http/Controllers/
│   ├── Livewire/               # Livewire components
│   ├── Models/
│   ├── Jobs/                   # Queued jobs
│   └── Events/                 # Broadcast events
├── database/
│   ├── migrations/
│   ├── factories/
│   └── seeders/
├── resources/
│   ├── css/app.css             # Tailwind entry point
│   ├── js/
│   │   ├── app.js
│   │   └── echo.js             # Laravel Echo / Reverb client config
│   └── views/
├── routes/
│   ├── web.php
│   └── channels.php            # Reverb broadcast channel definitions
├── config/
│   ├── broadcasting.php        # Reverb configured as default broadcaster
│   └── reverb.php
├── .lando.yml                  # Lando (Docker) service definitions
└── vite.config.js
```

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
| `VITE_REVERB_HOST` | `localhost` | Browser (reaches Reverb via Lando port-forward on `localhost:8080`) |

Docker service names are not resolvable from the browser, so these must stay separate.

### Internal Docker Hostnames

Services communicate by Docker service name, not `localhost`:

| Service | Internal Hostname | External (Host Machine) |
|---|---|---|
| MariaDB | `database` | `127.0.0.1:3306` |
| Redis | `cache` | `127.0.0.1:6379` |
| Reverb | `reverb` | `localhost:8080` |

---

## YouTube Series

This project is built live across a YouTube series. Each commit maps to a video episode — follow along to see every decision made from scratch.

https://www.youtube.com/playlist?list=PLCFAvrjCdis-mdDgzj3wAYA6wXjzgml9z
