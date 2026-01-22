FROM php:8.2-apache

# Enable Apache mod_rewrite (optional, but good practice)
RUN a2enmod rewrite

# Copy application source code to the container
COPY . /var/www/html/

# Set strict permissions
# The 'www-data' user needs write access to create 'tracker.sqlite'
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html