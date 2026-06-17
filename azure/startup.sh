#!/bin/bash
set -euo pipefail

TARGET="/etc/nginx/sites-enabled/default"
BASELINE="/home/site/nginx-default.baseline"
LOG="/home/site/nginx-startup.log"

exec >>"$LOG" 2>&1
echo "=== nginx startup $(date -u +%Y-%m-%dT%H:%M:%SZ) ==="

if [[ ! -f "$BASELINE" ]]; then
    cp "$TARGET" "$BASELINE"
    echo "Saved baseline nginx config"
fi

cp "$BASELINE" "$TARGET"

python3 <<'PY'
from pathlib import Path

path = Path("/etc/nginx/sites-enabled/default")
text = path.read_text()

if "map $http_x_forwarded_proto $nutraaxis_redirect_scheme" not in text:
    text = text.replace(
        "server {",
        "map $http_x_forwarded_proto $nutraaxis_redirect_scheme {\n"
        "    default $scheme;\n"
        "    https https;\n"
        "    http http;\n"
        "}\n\n"
        "server {",
        1,
    )

if "absolute_redirect off" not in text:
    if "port_in_redirect off;" in text:
        text = text.replace(
            "port_in_redirect off;",
            "port_in_redirect off;\n    absolute_redirect off;",
            1,
        )
    else:
        text = text.replace(
            "root /home/site/wwwroot;",
            "port_in_redirect off;\n"
            "    absolute_redirect off;\n"
            "    root /home/site/wwwroot;",
            1,
        )

if "error_page 404 /index.php" not in text:
    text = text.replace(
        "error_page   500 502 503 504  /50x.html;",
        "error_page 404 /index.php?$args;\n    error_page   500 502 503 504  /50x.html;",
        1,
    )

if "nutraaxis_directory_index" not in text:
    directory_rewrite = (
        "        # nutraaxis_directory_index — route /path/ directly to /path/index.php\n"
        "        rewrite ^/(.+?)/$ /$1/index.php last;\n"
    )
    text = text.replace(
        "location / {",
        "location / {\n" + directory_rewrite,
        1,
    )

if "try_files $uri $uri/" not in text:
    marker = "index  index.php index.html index.htm hostingstart.html;"
    replacement = marker + "\n        try_files $uri $uri/ /index.php?$args;"
    if marker in text:
        text = text.replace(marker, replacement, 1)

if "fastcgi_param HTTPS on" not in text:
    marker = "fastcgi_param QUERY_STRING $query_string;"
    replacement = marker + "\n        fastcgi_param HTTPS on;\n        fastcgi_param SERVER_PORT 443;"
    if marker in text:
        text = text.replace(marker, replacement, 1)

old_rules = [
    (
        "        if ($request_uri ~ ^/(.+[^/])$) {\n"
        "            return 301 $nutraaxis_redirect_scheme://$host$uri/;\n"
        "        }"
    ),
    (
        "        if ($uri ~ /[^/]$) {\n"
        "            return 301 $nutraaxis_redirect_scheme://$host$uri/$is_args$args;\n"
        "        }"
    ),
]
new_rule = (
    "        # nutraaxis_trailing_slash — relative redirect avoids http:// cache traps\n"
    "        if ($uri ~ /[^/]$) {\n"
    "            return 301 $uri/$is_args$args;\n"
    "        }"
)

for old_rule in old_rules:
    if old_rule in text:
        text = text.replace(old_rule, new_rule, 1)

if "nutraaxis_trailing_slash" not in text:
    text = text.replace(
        "location / {",
        "location / {\n" + new_rule,
        1,
    )

if "add_header Cache-Control" not in text:
    text = text.replace(
        "server_name  example.com www.example.com;",
        "server_name  example.com www.example.com;\n"
        "    add_header Cache-Control \"no-store, no-cache, must-revalidate\" always;",
        1,
    )

path.write_text(text)
PY

nginx -t
service nginx reload
echo "nginx reloaded"
