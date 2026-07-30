#!/bin/sh
set -eu

curl --fail --silent --show-error http://localhost/wp-login.php >/dev/null
test -f /var/www/html/.petshop-runtime-manifest
