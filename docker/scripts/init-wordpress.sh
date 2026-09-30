#!/bin/sh
set -eu

: "${WORDPRESS_DB_HOST:=db:3306}"
: "${WORDPRESS_DB_NAME:=wordpress}"
: "${WORDPRESS_DB_USER:=wordpress}"
: "${WORDPRESS_DB_PASSWORD:?WORDPRESS_DB_PASSWORD must be set}"
: "${WORDPRESS_URL:=http://localhost:8888}"
: "${WORDPRESS_TITLE:=Petshop Local}"
: "${WORDPRESS_ADMIN_USER:=admin}"
: "${WORDPRESS_ADMIN_PASSWORD:?WORDPRESS_ADMIN_PASSWORD must be set}"
: "${WORDPRESS_ADMIN_EMAIL:=wordpress@example.com}"

runtime=/var/www/html
plugins="$runtime/wp-content/plugins"
themes="$runtime/wp-content/themes"
personalizations="${PETSHOP_PERSONALIZATION_STORAGE:-/var/petshop-personalizations}"

until mysqladmin ping -h "${WORDPRESS_DB_HOST%:*}" -u"$WORDPRESS_DB_USER" -p"$WORDPRESS_DB_PASSWORD" --silent; do
  sleep 2
done

mkdir -p "$runtime" "$plugins" "$themes" "$runtime/wp-content/uploads"
cp -a /usr/src/wordpress/. "$runtime/"
cp -a /opt/dependencies/plugins/. "$plugins/"
cp -a /opt/dependencies/themes/. "$themes/"
if [ ! -d "$plugins/petshop-core" ]; then
  cp -a /opt/project-source/plugins/petshop-core "$plugins/"
fi
for plugin in melhor-envio-cotacao woo-better-shipping-calculator-for-brazil; do
  rm -rf "$plugins/$plugin"
  cp -a "/opt/project-source/plugins/$plugin" "$plugins/"
done
if [ ! -d "$themes/petshop-theme" ]; then
  cp -a /opt/project-source/themes/petshop-theme "$themes/"
fi
chown -R www-data:www-data "$runtime/wp-content"

# Private personalization storage lives outside the document root (Plano 012).
mkdir -p "$personalizations"
chown www-data:www-data "$personalizations"
chmod 750 "$personalizations"

if ! wp core is-installed --path="$runtime" --allow-root >/dev/null 2>&1; then
  wp config create --path="$runtime" --allow-root --skip-check --dbname="$WORDPRESS_DB_NAME" --dbuser="$WORDPRESS_DB_USER" --dbpass="$WORDPRESS_DB_PASSWORD" --dbhost="$WORDPRESS_DB_HOST"
  wp config set WP_DEBUG true --raw --type=constant --path="$runtime" --allow-root
  wp config set WP_DEBUG_LOG true --raw --type=constant --path="$runtime" --allow-root
  wp config set WP_DEBUG_DISPLAY false --raw --type=constant --path="$runtime" --allow-root
  wp config set SCRIPT_DEBUG true --raw --type=constant --path="$runtime" --allow-root
  wp config set WP_ENVIRONMENT_TYPE local --type=constant --path="$runtime" --allow-root
  wp config set WP_DEVELOPMENT_MODE all --type=constant --path="$runtime" --allow-root
  wp config set DISALLOW_FILE_EDIT true --raw --type=constant --path="$runtime" --allow-root
  wp core install --path="$runtime" --allow-root --skip-email --url="$WORDPRESS_URL" --title="$WORDPRESS_TITLE" --admin_user="$WORDPRESS_ADMIN_USER" --admin_password="$WORDPRESS_ADMIN_PASSWORD" --admin_email="$WORDPRESS_ADMIN_EMAIL"
fi

wp option update home "$WORDPRESS_URL" --path="$runtime" --allow-root
wp option update siteurl "$WORDPRESS_URL" --path="$runtime" --allow-root
wp option update timezone_string 'America/Sao_Paulo' --path="$runtime" --allow-root
wp option update permalink_structure '/%postname%/' --path="$runtime" --allow-root
wp rewrite flush --path="$runtime" --allow-root
wp language core install pt_BR --activate --path="$runtime" --allow-root || true
wp language plugin install woocommerce pt_BR --path="$runtime" --allow-root || true
wp language plugin install blocksy-companion pt_BR --path="$runtime" --allow-root || true
wp language plugin install fluentform pt_BR --path="$runtime" --allow-root || true
wp language plugin install stackable-ultimate-gutenberg-blocks pt_BR --path="$runtime" --allow-root || true
wp language theme install blocksy pt_BR --path="$runtime" --allow-root || true
wp plugin activate woocommerce blocksy-companion stackable-ultimate-gutenberg-blocks fluentform petshop-core woo-better-shipping-calculator-for-brazil melhor-envio-cotacao --path="$runtime" --allow-root

if ! wp option get petshop_shipping_dependencies_036_configured --path="$runtime" --allow-root >/dev/null 2>&1; then
  wp option update woo_better_calc_enable_product_page no --path="$runtime" --allow-root
  wp option update woo_better_calc_enable_cart_page no --path="$runtime" --allow-root
  wp option update woo_better_calc_enable_auto_address_fill no --path="$runtime" --allow-root
  wp option update petshop_shipping_dependencies_036_configured 1 --path="$runtime" --allow-root
fi

wp theme activate petshop-theme --path="$runtime" --allow-root

wp core version --path="$runtime" --allow-root > "$runtime/.petshop-runtime-manifest"
wp plugin list --path="$runtime" --allow-root --format=csv >> "$runtime/.petshop-runtime-manifest"
wp theme list --path="$runtime" --allow-root --format=csv >> "$runtime/.petshop-runtime-manifest"
chown www-data:www-data "$runtime/.petshop-runtime-manifest"
