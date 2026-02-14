# =============================================================================
# File Image Manager - Docker Image
# Multi-stage build: Node (frontend build) → PHP 8.5 + Nginx
# =============================================================================

# --- Stage 1: Build frontend assets -----------------------------------------
FROM node:22-alpine AS frontend-build

WORKDIR /build

# Install dependencies first (cache layer)
COPY package.json package-lock.json ./
RUN npm ci --legacy-peer-deps

# Copy frontend source + configs and build
COPY frontend/ frontend/
COPY vite.config.ts tsconfig.json ./
RUN npx vue-tsc --noEmit && npx vite build


# --- Stage 2: Production image with PHP 8.5-FPM + Nginx --------------------
FROM php:8.4-fpm-alpine

# Install system dependencies + PHP extensions
RUN apk add --no-cache \
        nginx \
        supervisor \
        freetype-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        libwebp-dev \
        libavif-dev \
        curl-dev \
        oniguruma-dev \
    && docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp \
        --with-avif \
    && docker-php-ext-install -j$(nproc) \
        gd \
        mbstring \
        curl \
        fileinfo \
        opcache \
    && rm -rf /var/cache/apk/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files and install PHP dependencies
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress

# Copy application source
COPY src/ src/
COPY config/ config/
COPY lang/ lang/
COPY public/index.php public/index.php
COPY public/tinymce/ public/tinymce/
COPY index.html ./
COPY demo.html tinymce.html ./

# Copy built frontend assets from stage 1
COPY --from=frontend-build /build/public/assets/ public/assets/

# Create media directories with proper permissions
RUN mkdir -p media/source media/thumbs \
    && chown -R www-data:www-data media/ \
    && chmod -R 755 media/

# Copy Nginx configuration
COPY docker/nginx.conf /etc/nginx/http.d/default.conf

# Copy Supervisor configuration
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Configure PHP for production
RUN { \
        echo 'upload_max_filesize = 64M'; \
        echo 'post_max_size = 64M'; \
        echo 'memory_limit = 256M'; \
        echo 'max_execution_time = 120'; \
        echo 'expose_php = Off'; \
    } > /usr/local/etc/php/conf.d/filemanager.ini \
    && { \
        echo 'opcache.enable=1'; \
        echo 'opcache.memory_consumption=128'; \
        echo 'opcache.max_accelerated_files=10000'; \
        echo 'opcache.validate_timestamps=0'; \
    } > /usr/local/etc/php/conf.d/opcache.ini

# Set ownership
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
