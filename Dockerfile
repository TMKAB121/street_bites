# syntax=docker/dockerfile:1

# ---- vendor: composer install (--no-scripts, artisan isn't bootstrappable
# with no .env yet at build time) -------------------------------------------
FROM composer:2 AS vendor
WORKDIR /app
COPY . .
# --ignore-platform-reqs: this stage only resolves/downloads packages, it never
# executes them — the runtime stage below has the real gd/redis/pdo_mysql
# extensions installed and is what actually runs the app.
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --optimize-autoloader \
    --ignore-platform-reqs

# ---- assets: Vite build only, never ships into the runtime image -----------
FROM node:20-alpine AS assets
WORKDIR /app
# Browser-facing Reverb config. Vite inlines import.meta.env.VITE_* into the
# bundle at build time, so the runtime REVERB_* task-definition env can never
# reach it — these must arrive here as --build-arg (release-deploy.yml). ARGs
# are exposed as env to RUN, which is how Vite picks them up.
ARG VITE_REVERB_APP_KEY
ARG VITE_REVERB_HOST
ARG VITE_REVERB_PORT
ARG VITE_REVERB_SCHEME
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

# ---- runtime: PHP-FPM + nginx, no Composer/Node in the final image ----------
FROM php:8.3-fpm-alpine AS runtime

RUN apk add --no-cache \
        nginx \
        supervisor \
        libpng \
        libjpeg-turbo \
        freetype \
        libwebp \
        libzip \
        icu-libs \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        libwebp-dev \
        libzip-dev \
        icu-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        gd \
        zip \
        intl \
        bcmath \
        pcntl \
        exif \
        opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

WORKDIR /var/www/html

COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build
COPY . .

COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/www.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 8080

# Overridden per ECS task definition: nginx+php-fpm for `web`,
# `php artisan reverb:start` for `reverb`, `php artisan queue:work redis
# --tries=3` for `queue-worker` — same image, three ECS services.
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf", "-n"]
