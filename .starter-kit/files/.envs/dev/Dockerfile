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

# Overwtite FrankenPHP binary
RUN php artisan octane:install --server=frankenphp --no-interaction

# Complete ZSH setup with user configuration
COPY devtools/zsh /tmp/zsh-config/
RUN chmod +x /tmp/zsh-config/setup-zsh.sh && /tmp/zsh-config/setup-zsh.sh && rm -rf /tmp/zsh-config

# Override Laravel configuration files with your customizations (if needed)
COPY backend/.envs/dev/laravel/start-container /usr/local/bin/start-container
COPY backend/.envs/dev/laravel/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY backend/.envs/dev/laravel/php.ini /etc/php/8.4/cli/conf.d/99-sail.ini
RUN chmod +x /usr/local/bin/start-container

# Override environment variables for your setup
ENV WORKDIR="/workspaces/backend"
ENV WEBSERVER=octane-watch
ENV SUPERVISOR_PHP_COMMAND="/usr/bin/php -d variables_order=EGPCS artisan serve --host=0.0.0.0 --port=80"

# Set working directory
WORKDIR $WORKDIR

# Expose ports
EXPOSE 80/tcp

# Final runtime configuration
ENTRYPOINT ["start-container"]