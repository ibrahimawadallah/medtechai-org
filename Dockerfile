FROM php:8.1-apache

# Enable Apache mod_rewrite for .htaccess support
RUN a2enmod rewrite

# Install PHP extensions needed by the API handler (curl)
RUN docker-php-ext-install curl

# Copy all project files to Apache web root
COPY . /var/www/html/

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html/ && \
    chmod -R 755 /var/www/html/

# Enable .htaccess overrides in Apache config
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Expose port 80
EXPOSE 80

# Start Apache in foreground
CMD ["apache2-foreground"]
