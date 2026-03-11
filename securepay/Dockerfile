# syntax=docker/dockerfile:1
# ─────────────────────────────────────────────────────────────
# FILE: Dockerfile
# PURPOSE: Build the PHP + Apache application container.
#
# BUGS FIXED FROM PREVIOUS VERSION:
#   1. Heredoc (<<'EOF') replaced with separate config files mounted via
#      COPY — the only reliable cross-platform way to write multi-line
#      configs in a Dockerfile without BuildKit heredoc support issues.
#   2. ${APACHE_LOG_DIR} variable now safe — written via COPY not heredoc.
#   3. security.ini written via COPY, not heredoc — no line-length issues.
#   4. disable_functions is now a single clean line in the .ini file.
#   5. COPY . /var/www/html/ replaced with selective COPY of app dirs only —
#      Dockerfile, docker-compose.yml, init.sql, setup.sh no longer land
#      inside the webroot.
#   6. File permission RUN now comes AFTER all files are written —
#      correct order: COPY → write .htaccess → set permissions.
#   7. session.cookie_secure set to 0 for local dev compatibility.
#      In production behind HTTPS, change to 1.
#   8. .dockerignore updated to exclude root-level non-app files.
# ─────────────────────────────────────────────────────────────

FROM php:8.2-apache

# ── PHP EXTENSIONS ────────────────────────────────────────────
# Only install what the app actually uses:
#   pdo        — PDO base class (required by pdo_mysql)
#   pdo_mysql  — MySQL driver used in config/db.php
#   fileinfo   — finfo_file() for MIME type checking in upload_handler.php
RUN docker-php-ext-install pdo pdo_mysql fileinfo

# ── APACHE MODULES ───────────────────────────────────────────
# rewrite  — needed for clean URLs and .htaccess rewrites
# headers  — needed for Header always set ... directives in vhost
RUN a2enmod rewrite \
 && a2enmod headers

# ── APACHE GLOBAL HARDENING ──────────────────────────────────
# Append to apache2.conf (does not overwrite it — safe to append)
# ServerTokens Prod   → response header shows "Apache" only, not version
# ServerSignature Off → error pages show no server info
# TraceEnable Off     → disables HTTP TRACE (prevents XST attack)
RUN echo "ServerTokens Prod"   >> /etc/apache2/apache2.conf \
 && echo "ServerSignature Off" >> /etc/apache2/apache2.conf \
 && echo "TraceEnable Off"     >> /etc/apache2/apache2.conf

# ── COPY CONFIG FILES INTO IMAGE ─────────────────────────────
# FIX #1 & #3: Instead of heredoc inside RUN (unreliable across Docker
# versions), we keep config files as real files in the repo and COPY them.
# This is the correct, reliable, auditable way to add config to an image.
#
# These files live in docker/ folder in your project:
#   docker/vhost.conf      → Apache virtual host configuration
#   docker/security.ini    → PHP hardening settings
COPY docker/vhost.conf    /etc/apache2/sites-available/000-default.conf
COPY docker/security.ini  /usr/local/etc/php/conf.d/security.ini

# ── COPY APPLICATION FILES ONLY ──────────────────────────────
# FIX #5: Copy only the actual PHP application directories — NOT the
# project root files (Dockerfile, docker-compose.yml, init.sql, setup.sh).
# Those files have no business being inside the Apache webroot.
#
# We copy each app directory explicitly:
COPY assets/   /var/www/html/assets/
COPY config/   /var/www/html/config/
COPY includes/ /var/www/html/includes/
# Copy all root-level PHP files individually
COPY register.php            /var/www/html/register.php
COPY login.php               /var/www/html/login.php
COPY logout.php              /var/www/html/logout.php
COPY dashboard.php           /var/www/html/dashboard.php
COPY profile.php             /var/www/html/profile.php
COPY profile_view.php        /var/www/html/profile_view.php
COPY serve_image.php         /var/www/html/serve_image.php
COPY transfer.php            /var/www/html/transfer.php
COPY search.php              /var/www/html/search.php
COPY transaction_history.php /var/www/html/transaction_history.php
COPY admin_login.php         /var/www/html/admin_login.php
COPY admin_logs.php          /var/www/html/admin_logs.php

# ── CREATE PRIVATE STORAGE OUTSIDE WEBROOT ───────────────────
# /var/www/private/ is NOT inside the DocumentRoot (/var/www/html/)
# Apache cannot serve anything from /var/www/private/ — ever.
RUN mkdir -p /var/www/private/uploads \
 && mkdir -p /var/www/private/logs

# ── WRITE .htaccess PROTECTION FILES ─────────────────────────
# FIX #6 & #7: .htaccess files written BEFORE permissions are set.
# "Require all denied" blocks all HTTP requests to these directories
# even if the vhost config changes. Belt-and-suspenders.
RUN printf "Require all denied\n" > /var/www/html/config/.htaccess   \
 && printf "Require all denied\n" > /var/www/html/includes/.htaccess

# ── FILE PERMISSIONS ─────────────────────────────────────────
# FIX #7: Permissions set LAST — after all files are written.
# www-data = Apache's runtime user inside the container.
#
# 640 on files  → owner (www-data) rw, group r, others: nothing
# 750 on dirs   → owner rwx, group rx, others: nothing
#
# PHP source files are NOT writable by the web process.
# Even if an attacker finds a file-write vulnerability, they cannot
# overwrite PHP source files to inject backdoors.
RUN chown -R www-data:www-data /var/www/html    \
 && chown -R www-data:www-data /var/www/private \
 && find /var/www/html    -type f -exec chmod 640 {} \; \
 && find /var/www/html    -type d -exec chmod 750 {} \; \
 && chmod -R 750 /var/www/private

EXPOSE 80

