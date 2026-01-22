FROM php:8.2-apache

RUN a2enmod rewrite

# Create a dedicated directory for the database
RUN mkdir -p /var/www/db

# Set ownership of the DB folder to www-data (Web Server User)
RUN chown -R www-data:www-data /var/www/db

# Copy application source code
COPY . /var/www/html/
