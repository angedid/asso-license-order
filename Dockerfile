FROM php:8.4-cli-alpine

# Install system dependencies and build libraries
RUN apk add --no-cache \
    bash \
    curl \
    git \
    libpq-dev \
    libzip-dev \
    linux-headers \
    unzip \
    zip \
    postgresql-client

# Install PHP extensions required by Laravel, PostgreSQL, and RabbitMQ
RUN docker-php-ext-install \
    bcmath \
    pcntl \
    pdo \
    pdo_pgsql \
    pgsql \
    sockets \
    zip

# Install Composer from official Composer image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files first for caching
COPY composer.json composer.lock ./

# Install project dependencies
RUN composer install --no-dev --no-interaction --no-scripts --prefer-dist --optimize-autoloader

# Copy application code
COPY . .

# Run post-autoload scripts and set permissions
RUN composer dump-autoload --optimize \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Expose Laravel default port
EXPOSE 8000

# Default entrypoint starts artisan serve
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
