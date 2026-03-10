# ============================================================
# Dockerfile — TransactiWar
# PHP 8.2 + Apache
# ============================================================
FROM php:8.2-apache

# Install dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev libjpeg-dev libwebp-dev \
    default-mysql-client \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql mysqli gd

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Apache config — allow .htaccess and set document root
RUN echo '<Directory /var/www/html>\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' > /etc/apache2/conf-available/transactiwar.conf \
 && a2enconf transactiwar

# Copy application files
COPY ./php/ /var/www/html/

# Permissions
RUN chown -R www-data:www-data /var/www/html \
 && chmod -R 755 /var/www/html \
 && mkdir -p /var/www/html/uploads/profiles \
 && chown -R www-data:www-data /var/www/html/uploads \
 && chmod 750 /var/www/html/uploads

# PHP security hardening
RUN { \
    echo "expose_php = Off"; \
    echo "display_errors = Off"; \
    echo "log_errors = On"; \
    echo "error_log = /var/log/php_errors.log"; \
    echo "session.cookie_httponly = 1"; \
    echo "session.cookie_samesite = Strict"; \
    echo "session.use_strict_mode = 1"; \
} >> /usr/local/etc/php/php.ini

# Copy & set entrypoint
COPY ./docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80
ENTRYPOINT ["/entrypoint.sh"]
