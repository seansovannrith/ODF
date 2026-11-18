# Laravel API image.
# All PHP packages are installed while the image is BUILT, so a failure shows up
# clearly during `docker compose build` instead of a silent crash at start.
FROM php:8.3-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip curl libzip-dev libpng-dev libicu-dev \
    && docker-php-ext-install pdo_mysql zip gd intl bcmath \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_MEMORY_LIMIT=-1

# Allow uploads of scanned documents up to 50 MB
RUN { \
      echo "upload_max_filesize=50M"; \
      echo "post_max_size=60M"; \
      echo "memory_limit=512M"; \
      echo "date.timezone=Asia/Phnom_Penh"; \
    } > /usr/local/etc/php/conf.d/odf.ini

WORKDIR /var/www/html

# 1) dependencies first (cached between builds)
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader

# 2) the application
COPY . .
RUN mkdir -p storage/app/public storage/app/private storage/framework/cache/data \
                storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chmod -R 777 storage bootstrap/cache \
    && composer dump-autoload --optimize --no-dev \
    && sed -i 's/\r$//' docker-entrypoint.sh \
    && cp docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh \
    && chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 8000
ENTRYPOINT ["docker-entrypoint.sh"]
