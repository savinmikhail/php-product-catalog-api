FROM php:8.3-fpm-alpine

RUN docker-php-ext-install pdo_mysql pdo_sqlite

WORKDIR /var/www/html
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock* ./
RUN composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader
COPY . .
RUN composer dump-autoload --no-dev --optimize

CMD ["php-fpm", "-F"]
