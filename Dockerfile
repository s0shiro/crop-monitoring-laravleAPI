# Use official PHP image with necessary extensions
FROM php:8.2-fpm

# Set working directory
WORKDIR /var/www

# Install system dependencies
RUN apt-get update && apt-get install -y \
    build-essential \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    git \
    curl \
    libpq-dev \
    libzip-dev \
    supervisor \
    && docker-php-ext-install pdo pdo_pgsql mbstring zip exif pcntl

# Install Composer
COPY --from=composer:2.5 /usr/bin/composer /usr/bin/composer

# Copy project files
COPY . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Set up supervisor configuration
COPY docker/supervisor.conf /etc/supervisor/conf.d/supervisor.conf

# Set permissions
RUN chown -R www-data:www-data /var/www \
    && chmod -R 755 /var/www/storage

# Create supervisor log directory
RUN mkdir -p /var/log/supervisor

# Expose port
EXPOSE 8000

# Start supervisor (which will manage both PHP-FPM and Laravel scheduler)
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/supervisord.conf"]
