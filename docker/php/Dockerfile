# PHP-FPM 8.3 Docker image for Gibbon CMS
# LAYA Kindergarten & Childcare Management Platform
#
# This image includes all PHP extensions required by Gibbon CMS:
# - PDO/MySQL for database connectivity
# - GD for image processing
# - intl for internationalization
# - zip for file handling
# - opcache for performance

FROM php:8.3-fpm-alpine

LABEL maintainer="LAYA Team"
LABEL description="PHP-FPM 8.3 for Gibbon CMS"

# Install system dependencies
RUN apk add --no-cache \
    # GD dependencies
    freetype \
    freetype-dev \
    libjpeg-turbo \
    libjpeg-turbo-dev \
    libpng \
    libpng-dev \
    libwebp \
    libwebp-dev \
    # intl dependencies
    icu-libs \
    icu-dev \
    # zip dependencies
    libzip \
    libzip-dev \
    # Other dependencies
    oniguruma-dev \
    gettext-dev \
    # MySQL client for health checks
    mysql-client \
    # Git for composer
    git \
    # Needed for file uploads
    curl \
    # FastCGI for health checks
    fcgi

# Install PHP extensions
RUN docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_mysql \
        mysqli \
        gd \
        intl \
        zip \
        mbstring \
        gettext \
        opcache \
        bcmath

# Install APCu for caching
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install apcu \
    && docker-php-ext-enable apcu \
    && apk del .build-deps

# Clean up build dependencies to reduce image size
RUN apk del \
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    libwebp-dev \
    icu-dev \
    libzip-dev \
    oniguruma-dev \
    gettext-dev

# Set working directory
WORKDIR /var/www/html/gibbon

# Set proper permissions for Gibbon uploads directory
RUN mkdir -p /var/www/html/gibbon/uploads \
    && chown -R www-data:www-data /var/www/html/gibbon

# Copy custom PHP configuration
COPY php.ini /usr/local/etc/php/conf.d/custom.ini

# Configure PHP-FPM
RUN echo 'pm.max_children = 50' >> /usr/local/etc/php-fpm.d/www.conf \
    && echo 'pm.start_servers = 5' >> /usr/local/etc/php-fpm.d/www.conf \
    && echo 'pm.min_spare_servers = 5' >> /usr/local/etc/php-fpm.d/www.conf \
    && echo 'pm.max_spare_servers = 35' >> /usr/local/etc/php-fpm.d/www.conf \
    && echo 'pm.max_requests = 500' >> /usr/local/etc/php-fpm.d/www.conf \
    && echo 'pm.status_path = /status' >> /usr/local/etc/php-fpm.d/www.conf \
    && echo 'ping.path = /ping' >> /usr/local/etc/php-fpm.d/www.conf \
    && echo 'ping.response = pong' >> /usr/local/etc/php-fpm.d/www.conf

# Create php-fpm-healthcheck script
RUN printf '#!/bin/sh\nSCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET cgi-fcgi -bind -connect 127.0.0.1:9000 | grep -q pong\n' > /usr/local/bin/php-fpm-healthcheck \
    && chmod +x /usr/local/bin/php-fpm-healthcheck

# Install bash for entrypoint script
RUN apk add --no-cache bash

# Copy and configure entrypoint script
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["php-fpm"]
