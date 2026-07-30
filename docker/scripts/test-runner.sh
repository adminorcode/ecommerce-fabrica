#!/bin/sh
set -eu

plugin=/var/www/html/wp-content/plugins/petshop-core
if [ ! -x "$plugin/vendor/bin/phpunit" ] || [ ! -f "$plugin/phpunit.xml.dist" ]; then
  echo 'PHPUnit e phpunit.xml.dist sao obrigatorios para executar a suite de testes.' >&2
  exit 2
fi

exec "$plugin/vendor/bin/phpunit" --configuration "$plugin/phpunit.xml.dist"
