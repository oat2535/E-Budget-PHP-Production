FROM dunglas/frankenphp:1-php8.3

# Environment configuration
ENV COMPOSER_ALLOW_SUPERUSER=1 \
    SERVER_NAME=":8000"

# Install required PHP extensions for Laravel (PostgreSQL PDO and Zip for Excel export)
# Streamlined to essential extensions with resilient native fallback
RUN set -eux; \
    install-php-extensions pdo_pgsql zip \
    || ( \
        apt-get update && \
        apt-get install -y --no-install-recommends libpq-dev libzip-dev && \
        docker-php-ext-install -j$(nproc) pdo_pgsql zip && \
        apt-get clean && rm -rf /var/lib/apt/lists/* \
    )

# Install Composer from official image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Configure production PHP settings
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && { \
        echo 'memory_limit = 512M'; \
        echo 'upload_max_filesize = 64M'; \
        echo 'post_max_size = 64M'; \
        echo 'max_execution_time = 300'; \
        echo 'date.timezone = Asia/Bangkok'; \
        echo 'opcache.enable = 1'; \
        echo 'opcache.enable_cli = 1'; \
        echo 'opcache.memory_consumption = 256'; \
        echo 'opcache.interned_strings_buffer = 16'; \
        echo 'opcache.max_accelerated_files = 20000'; \
        echo 'opcache.validate_timestamps = 0'; \
    } > "$PHP_INI_DIR/conf.d/99-ebudget.ini"

WORKDIR /app

# Copy Caddyfile configuration
COPY Caddyfile /etc/caddy/Caddyfile

# Copy composer dependency definitions first for Docker layer caching
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

# Copy full application codebase
COPY . .

# Ensure storage and bootstrap/cache directories exist with proper permissions
RUN mkdir -p storage/framework/cache/data \
             storage/framework/sessions \
             storage/framework/views \
             storage/logs \
             bootstrap/cache \
    && chmod -R 777 storage bootstrap/cache \
    && chmod +x docker-entrypoint.sh

EXPOSE 8000

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD curl -f http://localhost:8000/up || exit 1

ENTRYPOINT ["./docker-entrypoint.sh"]
