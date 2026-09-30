FROM wordpress:7.0.2-php8.3-apache@sha256:9fac4d47b61186131ffefb5d966f0045d0eea94bfd7bd40cafae29b78a709d1b

ARG WOOCOMMERCE_VERSION=10.9.4
ARG BLOCKSY_VERSION=2.1.50
ARG BLOCKSY_COMPANION_VERSION=2.1.50
ARG STACKABLE_VERSION=3.19.10
ARG FLUENT_FORMS_VERSION=6.2.9
ARG WP_CLI_VERSION=2.12.0
ARG COMPOSER_VERSION=2.8.9

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends curl unzip ca-certificates mariadb-client; \
    rm -rf /var/lib/apt/lists/*; \
    curl -fsSL -o /usr/local/bin/wp "https://github.com/wp-cli/wp-cli/releases/download/v${WP_CLI_VERSION}/wp-cli-${WP_CLI_VERSION}.phar"; \
    chmod +x /usr/local/bin/wp; \
    curl -fsSL -o /usr/local/bin/composer "https://getcomposer.org/download/${COMPOSER_VERSION}/composer.phar"; \
    chmod +x /usr/local/bin/composer; \
    mkdir -p /opt/dependencies/plugins /opt/dependencies/themes /opt/project-source; \
    curl -fsSL -o /tmp/woocommerce.zip "https://downloads.wordpress.org/plugin/woocommerce.${WOOCOMMERCE_VERSION}.zip"; \
    curl -fsSL -o /tmp/blocksy-companion.zip "https://downloads.wordpress.org/plugin/blocksy-companion.${BLOCKSY_COMPANION_VERSION}.zip"; \
    curl -fsSL -o /tmp/stackable.zip "https://downloads.wordpress.org/plugin/stackable-ultimate-gutenberg-blocks.${STACKABLE_VERSION}.zip"; \
    curl -fsSL -o /tmp/fluentform.zip "https://downloads.wordpress.org/plugin/fluentform.${FLUENT_FORMS_VERSION}.zip"; \
    curl -fsSL -o /tmp/blocksy.zip "https://downloads.wordpress.org/theme/blocksy.${BLOCKSY_VERSION}.zip"; \
    unzip -tq /tmp/woocommerce.zip; \
    unzip -tq /tmp/blocksy-companion.zip; \
    unzip -tq /tmp/stackable.zip; \
    unzip -tq /tmp/fluentform.zip; \
    unzip -tq /tmp/blocksy.zip; \
    unzip -q /tmp/woocommerce.zip -d /opt/dependencies/plugins; \
    unzip -q /tmp/blocksy-companion.zip -d /opt/dependencies/plugins; \
    unzip -q /tmp/stackable.zip -d /opt/dependencies/plugins; \
    unzip -q /tmp/fluentform.zip -d /opt/dependencies/plugins; \
    unzip -q /tmp/blocksy.zip -d /opt/dependencies/themes; \
    rm -f /tmp/*.zip

COPY docker/wordpress/php/development.ini /usr/local/etc/php/conf.d/zz-development.ini
COPY docker/scripts/init-wordpress.sh /usr/local/bin/init-wordpress
COPY docker/scripts/healthcheck-wordpress.sh /usr/local/bin/healthcheck-wordpress
COPY docker/scripts/backup.sh /usr/local/bin/backup.sh
COPY docker/scripts/restore.sh /usr/local/bin/restore.sh
COPY docker/scripts/test-runner.sh /usr/local/bin/test-runner
COPY wp-content/plugins/petshop-core /opt/project-source/plugins/petshop-core
COPY wp-content/plugins/melhor-envio-cotacao /opt/project-source/plugins/melhor-envio-cotacao
COPY wp-content/plugins/woo-better-shipping-calculator-for-brazil /opt/project-source/plugins/woo-better-shipping-calculator-for-brazil
COPY wp-content/themes/petshop-theme /opt/project-source/themes/petshop-theme

RUN set -eux; \
    chmod +x /usr/local/bin/init-wordpress /usr/local/bin/healthcheck-wordpress /usr/local/bin/backup.sh /usr/local/bin/restore.sh /usr/local/bin/test-runner; \
    if [ -f /opt/project-source/plugins/petshop-core/composer.lock ]; then \
      composer --working-dir=/opt/project-source/plugins/petshop-core install --prefer-dist --no-interaction --no-progress; \
    fi
