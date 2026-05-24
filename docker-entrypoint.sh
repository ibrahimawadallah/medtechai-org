#!/bin/bash
set -e

# Use Render's PORT env var (default to 3001 if not set)
PORT="${PORT:-3001}"

# Replace PORT in Apache configs
sed -i "s/\${PORT}/$PORT/g" /etc/apache2/ports.conf
sed -i "s/\${PORT}/$PORT/g" /etc/apache2/sites-available/000-default.conf

exec "$@"
