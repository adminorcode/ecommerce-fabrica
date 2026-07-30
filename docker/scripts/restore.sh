#!/bin/sh
set -eu

: "${BACKUP_DIRECTORY:=/backups}"
test -f "$BACKUP_DIRECTORY/database.sql"
test -f "$BACKUP_DIRECTORY/uploads.tar.gz"
wp db import "$BACKUP_DIRECTORY/database.sql" --allow-root --path=/var/www/html
tar -C /var/www/html/wp-content -xzf "$BACKUP_DIRECTORY/uploads.tar.gz"
wp rewrite flush --allow-root --path=/var/www/html
