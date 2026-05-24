FROM php:8.1-apache

# Enable Apache mod_rewrite for .htaccess support
RUN a2enmod rewrite

# Install system dependencies required by PHP extensions
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP curl extension
RUN docker-php-ext-install curl

# Copy all project files to Apache web root
COPY . /var/www/html/

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html/ && \
    chmod -R 755 /var/www/html/

# Enable .htaccess overrides in Apache config
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Use PORT env var (Render sets this, default 80)
RUN sed -i "s/^Listen 80/Listen \${PORT}/" /etc/apache2/ports.conf && \
    sed -i "s/^<VirtualHost \*:80>/<VirtualHost *:\${PORT}>/" /etc/apache2/sites-available/000-default.conf

# Copy startup script
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 3001

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
