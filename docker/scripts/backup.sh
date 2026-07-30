#!/bin/sh
set -eu

mkdir -p /backups
wp db export /backups/database.sql --allow-root --path=/var/www/html
tar -C /var/www/html/wp-content -czf /backups/uploads.tar.gz uploads
sha256sum /backups/database.sql /backups/uploads.tar.gz > /backups/manifest.txt
