#!/usr/bin/env bash
# Volcado de la base de datos a backups/db_AAAAMMDD_HHMM.sql
set -euo pipefail
export MSYS_NO_PATHCONV=1

cd "$(dirname "$0")/.."
mkdir -p backups

FILE="backups/db_$(date +%Y%m%d_%H%M).sql"

# Las credenciales las lee el propio contenedor (no pasan por el host)
docker compose exec -T db sh -c \
  'mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" --single-transaction --no-tablespaces "$MYSQL_DATABASE"' \
  > "$FILE"
 
echo "Backup creado: $FILE"
