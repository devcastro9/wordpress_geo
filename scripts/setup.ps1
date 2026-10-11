# Instalación del entorno de desarrollo (PowerShell, Windows)
# Uso: .\scripts\setup.ps1
# Si la política lo bloquea: powershell -ExecutionPolicy Bypass -File .\scripts\setup.ps1

# Ejecutar siempre desde la raíz del repo
Set-Location (Join-Path $PSScriptRoot "..")

if (-not (Test-Path .env)) { Copy-Item .env.example .env }

$wpPort = "8080"
$line = Get-Content .env | Where-Object { $_ -match '^WP_PORT=' } | Select-Object -First 1
if ($line) { $wpPort = ($line -split '=', 2)[1].Trim() }

$siteUrl       = "http://localhost:$wpPort"
$adminUser     = if ($env:ADMIN_USER)     { $env:ADMIN_USER }     else { "admin" }
$adminPassword = if ($env:ADMIN_PASSWORD) { $env:ADMIN_PASSWORD } else { "admin123" }
$adminEmail    = if ($env:ADMIN_EMAIL)    { $env:ADMIN_EMAIL }    else { "admin@example.com" }

# Ejecuta WP-CLI y devuelve el código de salida (sin lanzar excepciones)
function Wp {
  docker compose run --rm -T wpcli wp @args
}
 
# Igual que Wp, pero detiene el script si falla
function WpRun {
  Wp @args
  if ($LASTEXITCODE -ne 0) { throw "Falló: wp $($args -join ' ')" }
}

docker compose up -d
if ($LASTEXITCODE -ne 0) { throw "Falló docker compose up" }

Write-Host "Esperando a WordPress..."
$ready = $false
for ($i = 1; $i -le 40; $i++) {
  Wp config path *> $null
  if ($LASTEXITCODE -eq 0) { $ready = $true; break }
  Start-Sleep -Seconds 3
}
if (-not $ready) { throw "WordPress no respondió a tiempo. Revisa: docker compose logs wordpress" }

Wp core is-installed *> $null
if ($LASTEXITCODE -ne 0) {
  WpRun core install `
    "--url=$siteUrl" `
    "--title=Directorio de Negocios" `
    "--admin_user=$adminUser" `
    "--admin_password=$adminPassword" `
    "--admin_email=$adminEmail" `
    --skip-email
}

WpRun language core install es_ES --activate
WpRun rewrite structure '/%postname%/' --hard

# Tema padre y tema hijo
WpRun theme install twentytwentyfive
WpRun theme activate directorio-child

# Plugins de terceros
Get-Content scripts/plugins.txt |
  ForEach-Object { $_.Trim() } |
  Where-Object { $_ -and -not $_.StartsWith('#') } |
  ForEach-Object { WpRun plugin install $_ --activate }

# Plugin propio
WpRun plugin activate directorio-core

Write-Host "Listo: $siteUrl/wp-admin ($adminUser / $adminPassword)"