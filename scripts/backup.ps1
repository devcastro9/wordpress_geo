# Volcado de la base de datos a backups\db_AAAAMMDD_HHMM.sql
# Uso: .\scripts\backup.ps1
 
Set-Location (Join-Path $PSScriptRoot "..")
New-Item -ItemType Directory -Force -Path backups | Out-Null
 
$file = "backups/db_$(Get-Date -Format 'yyyyMMdd_HHmm').sql"

# No se usa ">" porque PowerShell 5.1 guardaría el archivo en UTF-16 y se corrompería.
# El volcado se genera dentro del contenedor y se copia después al host.
docker compose exec -T db sh -c 'mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" --single-transaction --no-tablespaces --result-file=/tmp/dump.sql "$MYSQL_DATABASE"'
if ($LASTEXITCODE -ne 0) { throw "Falló mysqldump" }
 
docker compose cp db:/tmp/dump.sql $file
if ($LASTEXITCODE -ne 0) { throw "Falló la copia del volcado" }
 
docker compose exec -T db rm -f /tmp/dump.sql
 
Write-Host "Backup creado: $file"
