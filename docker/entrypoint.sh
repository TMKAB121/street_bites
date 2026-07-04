#!/bin/sh
set -e

# bootstrap/cache/packages.php isn't shipped in the image (.dockerignore) since
# it's generated locally against dev dependencies; regenerate it here against
# the --no-dev vendor tree actually present in the container.
php artisan package:discover --ansi

# Real secrets/env vars land as container env at start (ECS task definition
# `secrets`/`environment`), so caching here — not at image build time — is the
# earliest point the real values exist. Migrations are deliberately NOT run
# here: they run as a separate one-off `ecs run-task` in release-deploy.yml so
# a multi-task `web` service can never race a concurrent `migrate`.
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec "$@"
