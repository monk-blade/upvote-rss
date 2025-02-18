#!/bin/sh

USER_ID=${USER_ID:-1000}
GROUP_ID=${GROUP_ID:-1000}

CURRENT_USER_ID=$(id -u upvote-rss 2>/dev/null)
CURRENT_GROUP_ID=$(getent group upvote-rss | cut -d: -f3 2>/dev/null)

if [ "$CURRENT_USER_ID" != "$USER_ID" ] || [ "$CURRENT_GROUP_ID" != "$GROUP_ID" ]; then
  if id -u upvote-rss > /dev/null 2>&1; then
    deluser upvote-rss
  fi

  if getent group upvote-rss; then
    delgroup upvote-rss
  fi

  addgroup -g $GROUP_ID upvote-rss
  adduser -D -u $USER_ID -G upvote-rss -s /bin/sh upvote-rss
fi

for dir in /app/cache /app/logs; do
  # Create the cache and logs directories if they don't exist
  if [ ! -d "$dir" ]; then
    echo "Creating $dir directory"
    mkdir -p "$dir"
  fi

  # Check and set ownership of directories
  if [ "$(stat -c %U $dir)" != "upvote-rss" ] || [ "$(stat -c %G $dir)" != "upvote-rss" ]; then
    echo "Setting ownership of $dir to upvote-rss:upvote-rss"
    chown -R upvote-rss:upvote-rss $dir
  fi

  # Set permissions of directories
  chmod 755 "$dir"
done

# Echo the app version to stdout
APP_VERSION=$(grep "const UPVOTE_RSS_VERSION" /app/config.php | cut -d "'" -f 2)
echo "Starting Upvote RSS v$APP_VERSION"

exec su-exec upvote-rss "$@"
