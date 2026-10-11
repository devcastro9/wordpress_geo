#!/usr/bin/env bash
# Instalación del entorno de desarrollo (Git Bash, WSL o Linux)
set -euo pipefail

# Evita que Git Bash convierta rutas como /var/www/html
export MSYS_NO_PATHCONV=1

# Ejecutar siempre desde la raíz del repo
cd "$(dirname "$0")/.."

[ -f .env ] || cp .env.example .env

WP_PORT="$(grep -E '^WP_PORT=' .env | cut -d= -f2 | tr -d '\r')"
WP_PORT="${WP_PORT:-8080}"
SITE_URL="http://localhost:${WP_PORT}"
ADMIN_USER="${ADMIN_USER:-admin}"
ADMIN_PASSWORD="${ADMIN_PASSWORD:-admin123}"
ADMIN_EMAIL="${ADMIN_EMAIL:-admin@example.com}"
 
docker compose up -d
 
wp() { docker compose run --rm -T wpcli wp "$@"; }
 
echo "Esperando a WordPress..."
for i in $(seq 1 40); do
  if wp config path >/dev/null 2>&1; then break; fi
  if [ "$i" -eq 40 ]; then
    echo "WordPress no respondió a tiempo. Revisa: docker compose logs wordpress" >&2
    exit 1
  fi
  sleep 3
done
 
if ! wp core is-installed >/dev/null 2>&1; then
  wp core install \
    --url="$SITE_URL" \
    --title="Directorio de Negocios" \
    --admin_user="$ADMIN_USER" \
    --admin_password="$ADMIN_PASSWORD" \
    --admin_email="$ADMIN_EMAIL" \
    --skip-email
fi
 
wp language core install es_ES --activate
wp rewrite structure '/%postname%/' --hard
 
# Tema padre y tema hijo
wp theme install twentytwentyfive
wp theme activate directorio-child
 
# Plugins de terceros (acepta archivos con saltos de línea de Windows)
while IFS= read -r plugin || [ -n "$plugin" ]; do
  plugin="${plugin%$'\r'}"
  [ -z "$plugin" ] && continue
  case "$plugin" in \#*) continue ;; esac
  wp plugin install "$plugin" --activate
done < scripts/plugins.txt
 
# Plugin propio
wp plugin activate directorio-core
 
echo "Listo: ${SITE_URL}/wp-admin (${ADMIN_USER} / ${ADMIN_PASSWORD})"