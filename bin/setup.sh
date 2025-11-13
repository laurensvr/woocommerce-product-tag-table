#!/usr/bin/env bash
set -euo pipefail

PROJECT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
cd "$PROJECT_DIR"

if ! command -v docker >/dev/null 2>&1; then
  echo "Docker is required to run this setup script." >&2
  exit 1
fi

COMPOSE=${COMPOSE:-docker compose}

$COMPOSE up -d db wordpress

# Wait for database to become ready.
echo "Waiting for database connection..."
until $COMPOSE exec -T db mariadb -uwordpress -pwordpress -e 'SELECT 1' >/dev/null 2>&1; do
  sleep 2
  printf '.'
done
echo

echo "Running WordPress installation..."
WP_CMD="$COMPOSE run --rm cli wp --allow-root"

if ! $WP_CMD core is-installed >/dev/null 2>&1; then
  $WP_CMD core install \
    --url="${WORDPRESS_HOME:-http://localhost:8080}" \
    --title="WooCommerce Product Tag Table Demo" \
    --admin_user="${WORDPRESS_ADMIN_USER:-admin}" \
    --admin_password="${WORDPRESS_ADMIN_PASSWORD:-admin}" \
    --admin_email="${WORDPRESS_ADMIN_EMAIL:-admin@example.com}" \
    --skip-email
fi

$WP_CMD plugin install woocommerce --activate
$WP_CMD plugin activate woocommerce-product-tag-table
$WP_CMD rewrite structure '/%postname%/' --hard
$WP_CMD option update blogdescription 'Demo omgeving voor WooCommerce Product Tag Table'

# Create sample WooCommerce store address to avoid wizard prompts.
$WP_CMD option update woocommerce_store_address 'Demo Straat 1'
$WP_CMD option update woocommerce_store_postcode '1000AA'
$WP_CMD option update woocommerce_store_city 'Amsterdam'
$WP_CMD option update woocommerce_default_country 'NL:NH'
$WP_CMD option update woocommerce_currency 'EUR'

# Populate demo data.
$WP_CMD eval-file /var/www/html/mock-data.php

echo "Setup complete. Visit http://localhost:8080 to view the site."
