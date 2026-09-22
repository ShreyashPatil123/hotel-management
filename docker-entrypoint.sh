#!/bin/sh
set -eu
PORT="${PORT:-10000}"
sed -i "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:.*>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf
exec apache2-foreground
