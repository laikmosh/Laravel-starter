# Multi-stage Dockerfile using pre-built Laravel Sail runtime image
# Uses community-maintained pre-built Laravel Sail images
# Source: https://github.com/ariaieboy/sail-runtime-image

# Import pre-built Laravel Sail runtime image (Ubuntu-based for better DevContainer compatibility)
FROM ariaieboy/sail-runtime-image:8.4-24 AS development

# ============================================
# Your Customizations Start Here
# ============================================

# Ensure we're running as root for installations
USER root

# Install additional development tools (Ubuntu packages)
RUN apt-get update && apt-get install -y \
    zsh \
    sudo \
    tmux \
    curl \
    git \
    && apt-get -y autoremove \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Override Laravel configuration files with your customizations (if needed)
COPY backend/.envs/prod/laravel/start-container /usr/local/bin/start-container
COPY backend/.envs/prod/laravel/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY backend/.envs/prod/laravel/php.ini /etc/php/8.4/cli/conf.d/99-sail.ini
RUN chmod +x /usr/local/bin/start-container

# Override environment variables for your setup
ENV WORKDIR="/workspaces/backend"
ENV WEBSERVER=octane-watch
ENV SUPERVISOR_PHP_COMMAND="/usr/bin/php -d variables_order=EGPCS artisan serve --host=0.0.0.0 --port=80"

# Set the working directory
WORKDIR /var/www/html

# Copy application files (ensure all directories are included)
COPY --chown=sail:sail backend/. /var/www/html/

# Create required directories if they don't exist and set permissions
RUN mkdir -p /var/www/html/storage/logs /var/www/html/storage/framework/sessions /var/www/html/storage/framework/cache /var/www/html/bootstrap/cache && \
    chmod -R 755 /var/www/html && \
    chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Note: public/storage symlink will be created by start-container script

# Install composer dependencies during build
RUN cd /var/www/html && \
    composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist && \
    composer dump-autoload --optimize --no-dev

# Install npm dependencies and build assets during build
RUN cd /var/www/html && \
    npm ci && \
    npm run build && \
    npm prune --production && \
    rm -rf node_modules/.cache

# Ensure correct ownership after builds
RUN chown -R sail:sail /var/www/html

# Expose ports
EXPOSE 80/tcp

# Final runtime configuration
ENTRYPOINT ["start-container"]