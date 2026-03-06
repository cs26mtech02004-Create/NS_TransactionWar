# Use an official PHP image with Apache
FROM php:8.2-apache

# Install PDO MySQL extension (Essential for secure database work)
RUN docker-php-ext-install pdo pdo_mysql

# Enable Apache rewrite module for clean URLs
RUN a2enmod rewrite
