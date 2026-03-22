# Multi-stage build for PHP application
FROM php:8.2-apache AS builder

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions required for MySQL and the application
RUN docker-php-ext-install \
    mysqli \
    pdo \
    pdo_mysql \
    opcache \
    && docker-php-ext-enable opcache

# Copy application code
COPY . /var/www/html/

# Production stage
FROM php:8.2-apache

# Install only runtime dependencies
RUN apt-get update && apt-get install -y \
    libonig5 \
    libxml2 \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install \
    mysqli \
    pdo \
    pdo_mysql \
    opcache

# Enable Apache modules needed for PHP
RUN a2enmod rewrite

# Create non-root user for security
RUN groupadd -g 1000 appuser && useradd -u 1000 -g appuser appuser

# Set proper permissions
RUN chown -R appuser:appuser /var/www/html
RUN chown -R appuser:appuser /var/log/apache2

# Copy OPcache configuration for production performance
RUN echo "opcache.enable=1\n\
opcache.memory_consumption=128\n\
opcache.interned_strings_buffer=8\n\
opcache.max_accelerated_files=4000\n\
opcache.revalidate_freq=60\n\
opcache.fast_shutdown=1" > /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini

# Configure Apache to serve from project root (access via localhost/public/)
RUN echo "<VirtualHost *:80>\n\
    ServerName localhost\n\
    DocumentRoot /var/www/html\n\
    <Directory /var/www/html>\n\
        Options -Indexes +FollowSymLinks\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
    <Directory /var/www/html/public>\n\
        Options -Indexes +FollowSymLinks\n\
        AllowOverride All\n\
        Require all granted\n\
        <IfModule mod_rewrite.c>\n\
            RewriteEngine On\n\
            RewriteBase /public/\n\
            RewriteCond %{REQUEST_FILENAME} !-f\n\
            RewriteCond %{REQUEST_FILENAME} !-d\n\
            RewriteRule ^(.*)$ /public/index.php [L]\n\
        </IfModule>\n\
    </Directory>\n\
    <FilesMatch \\.php$>\n\
        SetHandler application/x-httpd-php\n\
    </FilesMatch>\n\
    ErrorLog /var/log/apache2/error.log\n\
    CustomLog /var/log/apache2/access.log combined\n\
</VirtualHost>" > /etc/apache2/sites-available/000-default.conf

# Disable default site and enable the new one
RUN a2dissite 000-default 2>/dev/null || true && \
    a2ensite 000-default

# Copy application code from builder stage
COPY --from=builder --chown=appuser:appuser /var/www/html /var/www/html

# Expose port 80 for Apache
EXPOSE 80

# Use non-root user
USER appuser

# Start Apache in foreground
CMD ["apache2-foreground"]
