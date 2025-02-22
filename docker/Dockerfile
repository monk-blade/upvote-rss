FROM dunglas/frankenphp:1.4.4-php8.4.4-alpine AS build

# Install required utilities and PHP extensions
RUN apk add --no-cache --virtual .build-deps \
    build-base freetype-dev libjpeg-turbo-dev libpng-dev libwebp-dev && \
    install-php-extensions gd opcache && \
    apk del .build-deps && \
    rm -rf /var/cache/apk/*

# Final stage
FROM dunglas/frankenphp:1.4.4-php8.4.4-alpine

# Install su-exec and opcache
RUN apk add --no-cache su-exec && \
    install-php-extensions gd opcache

# Copy the application files
COPY . /app

# Create necessary directories and move files
RUN mkdir -p /app/cache /app/logs /data/caddy/locks && \
	mv /app/docker/Caddyfile /etc/caddy/Caddyfile && \
	mv /app/docker/entrypoint.sh /entrypoint.sh

# Make the entrypoint script executable
RUN chmod +x /entrypoint.sh

# Run on port 80
EXPOSE 80

# Add healthcheck
HEALTHCHECK --interval=30s --timeout=20s --start-period=5s --retries=3 \
  CMD curl --fail http://localhost:80 || exit 1

# Run the entrypoint script
ENTRYPOINT ["/entrypoint.sh"]

# Run the PHP server
CMD ["frankenphp", "php-server", "-r", "/app"]