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

# Copy application files
COPY . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# Build frontend assets
RUN npm install && npm run build

EXPOSE 9000

CMD ["php-fpm"]
