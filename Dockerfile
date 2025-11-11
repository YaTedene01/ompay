FROM php:8.2-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    unzip \
    zip \
    libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql mbstring zip exif pcntl

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy existing application
COPY . /var/www/html

# Install PHP dependencies
RUN composer install --no-interaction --no-dev --prefer-dist --optimize-autoloader || true

# Set permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache || true

EXPOSE 9000

CMD ["php-fpm"]
