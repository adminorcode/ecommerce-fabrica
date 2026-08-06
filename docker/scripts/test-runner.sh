#!/bin/sh
set -eu

plugin=/var/www/html/wp-content/plugins/petshop-core
phpunit=/opt/project-source/plugins/petshop-core/vendor/bin/phpunit
if [ ! -x "$phpunit" ] || [ ! -f "$plugin/phpunit.xml.dist" ]; then
  echo 'PHPUnit e phpunit.xml.dist sao obrigatorios para executar a suite de testes.' >&2
  exit 2
fi

exec "$phpunit" --configuration "$plugin/phpunit.xml.dist"
