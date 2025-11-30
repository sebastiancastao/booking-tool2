FROM php:8.2-fpm

# Install OS dependencies for PHP extensions + Node.js build
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libicu-dev \
    unzip \
    git \
    zip \
    zlib1g-dev \
    libonig-dev \
    ca-certificates \
    curl \
    && docker-php-ext-install intl zip bcmath pcntl \
    && curl -fsSL https://deb.nodesource.com/setup_18.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

# copy composer binary
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Allow overriding APP_URL at build or runtime (default is localhost)
ARG APP_URL=https://booking-tool2.onrender.com
ENV APP_URL=${APP_URL}

# Copy application files
COPY . .

# Apply PHP configuration overrides
COPY docker/php/conf.d/chalk.ini /usr/local/etc/php/conf.d/90-chalk.ini

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# Build frontend assets
RUN npm install && npm run build

EXPOSE 8000
# php artisan serve listens for HTTP traffic on Railway's PORT
CMD ["sh", "-c", "php artisan serve --host=0.0.0.0 --port=${PORT:-8000}"]
