# ==============================================================================
# GlobeCon LMS - Enterprise Production Dockerfile
# Base: PHP 8.2 with Apache & Moodle 4.5 LTS Core Engine
# Supports: Railway, Render, Fly.io, and Cloud VPS
# ==============================================================================

FROM php:8.2-apache

# Prevent interactive prompts during apt install
ENV DEBIAN_FRONTEND=noninteractive

# 1. Install OS-level build dependencies & libraries required by Moodle extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libicu-dev \
    libxml2-dev \
    libxslt1-dev \
    libpq-dev \
    mariadb-client \
    postgresql-client \
    curl \
    git \
    unzip \
    ca-certificates \
    && rm -rf /var/lib/apt/lists/*

# 2. Configure & install mandatory PHP extensions for Moodle 4.5
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        gd \
        zip \
        intl \
        mysqli \
        pdo_mysql \
        pgsql \
        pdo_pgsql \
        opcache \
        soap \
        exif \
        bcmath

# 3. Recommended PHP OPcache settings for high-performance Moodle
RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.memory_consumption=256'; \
        echo 'opcache.max_accelerated_files=10000'; \
        echo 'opcache.revalidate_freq=60'; \
        echo 'opcache.use_cwd=1'; \
        echo 'opcache.validate_timestamps=1'; \
        echo 'opcache.save_comments=1'; \
        echo 'opcache.enable_file_override=0'; \
        echo 'max_input_vars=5000'; \
        echo 'upload_max_filesize=100M'; \
        echo 'post_max_size=100M'; \
        echo 'memory_limit=512M'; \
        echo 'max_execution_time=300'; \
        echo 'date.timezone=Africa/Nairobi'; \
    } > /usr/local/etc/php/conf.d/moodle-recommended.ini

# 4. Download & unpack official Moodle 4.5 LTS core
ARG MOODLE_VERSION=MOODLE_405_STABLE
RUN curl -fsSL "https://github.com/moodle/moodle/archive/refs/heads/${MOODLE_VERSION}.tar.gz" | tar xz --strip-components=1 -C /var/www/html/

# 5. Enable Apache Rewrite Module
RUN a2enmod rewrite headers

# 6. Copy custom GlobeCon corporate theme, dashboard routing, and local customizations
COPY theme/globecon/ /var/www/html/theme/globecon/
COPY custom/moodle/ /var/www/html/
COPY custom/lang/ /var/www/moodledata_preset_lang/
COPY scripts/ /var/www/html/scripts/

# 7. Create data directory with proper Apache ownership
RUN mkdir -p /var/www/moodledata && chown -R www-data:www-data /var/www/moodledata /var/www/html

# 8. Copy and configure entrypoint script
COPY scripts/docker-entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# 9. Default HTTP Port (can be overridden dynamically by cloud PaaS $PORT)
EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
CMD ["apache2-foreground"]

