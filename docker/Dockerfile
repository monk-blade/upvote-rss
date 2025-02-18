FROM dunglas/frankenphp:1.4.2-php8.4.3-alpine

# Install required utilities and PHP extensions
RUN apk add --no-cache su-exec freetype-dev libjpeg-turbo-dev libpng-dev libwebp-dev && \
    apk add --no-cache --virtual .build-deps build-base
RUN install-php-extensions gd opcache
RUN addgroup -g ${GROUP_ID:-1000} upvote-rss && \
    adduser -S -u ${USER_ID:-1000} -G upvote-rss upvote-rss
RUN apk del .build-deps && \
    rm -rf /var/cache/apk/*

# Copy the application files and set the correct permissions
COPY classes /app/classes
COPY img /app/img
COPY inc /app/inc
COPY js /app/js
COPY styles /app/styles
COPY vendor /app/vendor
COPY views /app/views
COPY ajax.php app.php cache.php config.php favicon.ico functions.php index.php /app/
RUN chown -R upvote-rss:upvote-rss /app

# Copy the Caddyfile
COPY docker/Caddyfile /etc/caddy/Caddyfile

# Copy the entrypoint script and set the correct permissions
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh && chown upvote-rss:upvote-rss /entrypoint.sh

# Create necessary directories and set permissions
RUN mkdir -p /app/cache && chown -R upvote-rss:upvote-rss /app/cache
RUN mkdir -p /app/logs && chown -R upvote-rss:upvote-rss /app/logs
RUN mkdir -p /data/caddy/locks && chown -R upvote-rss:upvote-rss /data

# Run on port 80
EXPOSE 80

# Add healthcheck
HEALTHCHECK --interval=30s --timeout=20s --start-period=5s --retries=3 \
  CMD curl --fail http://localhost:80 || exit 1

# Run the entrypoint script
ENTRYPOINT ["/entrypoint.sh"]

# Run the PHP server
CMD ["frankenphp", "php-server", "-r", "/app"]