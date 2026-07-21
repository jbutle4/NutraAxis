#!/bin/bash
set -euo pipefail

# Persist nginx upload limit across App Service restarts (default is ~1MB → HTTP 413).
NGINX_DEFAULT="/etc/nginx/sites-enabled/default"
PERSISTED="/home/default"

if [ -f "$PERSISTED" ]; then
  cp "$PERSISTED" "$NGINX_DEFAULT"
else
  cp "$NGINX_DEFAULT" "$PERSISTED"
  if grep -q 'client_max_body_size' "$PERSISTED"; then
    sed -i 's/client_max_body_size[^;]*;/client_max_body_size 25m;/' "$PERSISTED"
  else
    sed -i '/server {/a \    client_max_body_size 25m;' "$PERSISTED"
  fi
  cp "$PERSISTED" "$NGINX_DEFAULT"
fi

if command -v nginx >/dev/null 2>&1; then
  nginx -t >/dev/null 2>&1 && service nginx reload >/dev/null 2>&1 || service nginx restart >/dev/null 2>&1 || true
fi
