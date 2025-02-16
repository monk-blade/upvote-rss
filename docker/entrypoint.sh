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

  chown -R upvote-rss:upvote-rss /app/cache
fi

exec su-exec upvote-rss "$@"
