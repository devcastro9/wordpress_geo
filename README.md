# Directorio de Clinicas
 
Directorio de negocios sobre WordPress con mapa (Leaflet/OpenStreetMap), gestionado con Docker. Monorepo con la infraestructura, el plugin y el tema propios.
 
- **Desarrollo:** Windows (Docker Desktop con backend WSL2)
- **Producción:** Linux (Docker Engine).

## Stack
 
| Servicio | Imagen | Uso |
|---|---|---|
| WordPress | `wordpress:7.1-php8.3-apache` | Aplicación |
| Base de datos | `mysql:lts` | Base de datos LTS v9 |
| WP-CLI | `wordpress:cli-php8.3` | Instalación y tareas (bajo demanda) |
| Mailpit | `axllent/mailpit` | Captura de correos (solo desarrollo) |
| Caddy | `caddy:2` | Proxy inverso y HTTPS (solo producción) |
 
## Requisitos
 
- Docker Desktop (Windows) o Docker Engine (Linux)
- Git
- Internet para descargar las imagenes
 
## Estructura
 
```
directorio-wp/
├── .github/workflows/validate.yml   # CI: sintaxis PHP, Compose, build, CRLF
├── docker-compose.yml               # base común
├── docker-compose.override.yml      # desarrollo (se carga solo)
├── docker-compose.prod.yml          # producción
├── Dockerfile                       # imagen de producción (código dentro)
├── Caddyfile                        # proxy HTTPS (producción)
├── uploads.ini                      # límites de subida de PHP
├── .env.example                     # plantilla de variables
├── scripts/
│   ├── setup.sh                     # instalación (Git Bash / Linux)
│   ├── setup.ps1                    # instalación (PowerShell)
│   ├── backup.sh                    # volcado de la base de datos
│   └── plugins.txt                  # plugins de terceros a instalar
└── wp-content/
    ├── plugins/directorio-core/     # plugin propio: CPT, campos, mapas
    └── themes/directorio-child/     # tema hijo
```
 
Solo `directorio-core` y `directorio-child` se versionan. El núcleo de WordPress, los plugins de terceros y `uploads` viven en volúmenes de Docker.
 
## Puesta en marcha (desarrollo)
 
```bash
git clone <url-del-repo> directorio-wp
cd directorio-wp
```
 
Después, según tu terminal:
 
```powershell
# PowerShell
.\scripts\setup.ps1
# Si la política de ejecución lo bloquea:
powershell -ExecutionPolicy Bypass -File .\scripts\setup.ps1
```
 
```bash
# Git Bash, WSL o Linux
bash scripts/setup.sh
```
 
El script crea `.env` a partir de `.env.example`, levanta los contenedores, instala WordPress en español, configura los enlaces permanentes, activa el tema hijo y los plugins.
 
> Antes de usarlo en cualquier entorno compartido, cambia las contraseñas de `.env` y la del administrador (`admin` / `admin123` es solo para desarrollo local).
 
## Accesos en desarrollo
 
| Servicio | URL / dirección |
|---|---|
| Sitio | http://localhost:8080 |
| Administración | http://localhost:8080/wp-admin |
| Mailpit (correos de prueba) | http://localhost:8025 |
| Base de datos (DBeaver) | `127.0.0.1:3307` |
 
Los puertos se publican solo en `127.0.0.1` (salvo WordPress), por lo que no quedan expuestos a la red local.
 
Para que WordPress envíe correo a Mailpit, configura el plugin WP Mail SMTP con host `mailpit`, puerto `1025`, sin autenticación.
 
## Comandos habituales
 
```bash
docker compose up -d                  # levantar
docker compose down                   # parar (conserva datos)
docker compose down -v                # parar y BORRAR datos locales
docker compose ps                     # estado
docker compose logs -f wordpress      # logs
docker compose up -d --remove-orphans # eliminar contenedores que ya no existen en los YAML
 
# WP-CLI
docker compose run --rm wpcli wp plugin list
docker compose run --rm wpcli wp cache flush
 
# Validar la configuración
docker compose config -q
docker compose -f docker-compose.yml -f docker-compose.prod.yml config -q
```
 
Errores de PHP: se registran en `wp-content/debug.log` dentro del contenedor (no se muestran en pantalla).
 
## Conexión a la base de datos con DBeaver
 
| Campo | Desarrollo | Producción |
|---|---|---|
| Host | `127.0.0.1` | `127.0.0.1` |
| Puerto | `3307` | `3306` |
| Base de datos | `DB_NAME` | `DB_NAME` |
| Usuario | `DB_USER` | `DB_USER` |
| Acceso | Directo | **Túnel SSH** al servidor |
 
Si aparece `Public Key Retrieval is not allowed`, en *Driver properties* pon `allowPublicKeyRetrieval=true` (solo en local).
 
En producción el puerto 3306 se publica únicamente en el loopback del servidor. Nunca lo abras en el firewall.
 
## Flujo de trabajo
 
1. Crea una rama: `git switch -c feature/nombre`.
2. Edita el plugin o el tema; los cambios se reflejan al instante en el contenedor.
3. Valida: `docker compose config -q`.
4. Commit con prefijos convencionales: `feat:`, `fix:`, `chore:`, `docs:`.
5. Push y pull request hacia `main`. El CI ejecuta las validaciones.
### Contenido y configuración
 
- Los **negocios** y los ajustes hechos desde el panel viven en la base de datos, no en Git.
- Los **campos de ACF** se guardan como JSON en `wp-content/plugins/directorio-core/acf-json/` y sí se versionan.
- El **CPT "Negocio"** y las taxonomías están en código dentro del plugin.
- Los cambios de estructura se hacen por código, no desde DBeaver, para que viajen a producción.

## Backups

```bash
bash scripts/backup.sh     # genera backups/db_AAAAMMDD_HHMM.sql
```

Los `.sql` y la carpeta `backups/` están en `.gitignore`. No subas volcados con datos reales.

## Despliegue en producción (Linux)

En el servidor se crea un `.env` propio con claves fuertes y `DOMAIN=tudominio.com`.

```bash
git clone <url-del-repo> directorio-wp
cd directorio-wp
cp .env.example .env        # editar con valores reales
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build
```

Diferencias respecto a desarrollo:

- El código va **dentro de la imagen** (`Dockerfile`), sin bind mounts.
- Caddy obtiene y renueva el certificado HTTPS automáticamente.
- No hay Mailpit, ni modo debug, ni `wpcli` activo.
Para migrar contenido de un entorno a otro, usa `wp db export` / `wp db import` y después:

```bash
wp search-replace 'http://localhost:8080' 'https://tudominio.com' --all-tables
```

### Servidor
 
- Abrir solo los puertos 22, 80 y 443 (`ufw`).
- SSH por clave, sin contraseña.
- Backups programados (`cron`) de la base de datos y del volumen `uploads`, con copia fuera del servidor.
- Actualizaciones de seguridad automáticas del sistema.
## Windows Server
 
Los contenedores Linux no corren de forma nativa en Windows Server. La vía recomendada es una **VM Ubuntu Server en Hyper-V** con Docker Engine, usando exactamente los mismos archivos de producción.
 
Checklist de validación:
 
1. Crear la VM (generación 2, switch externo o NAT con reenvío de 80/443).
2. Instalar Docker Engine y el plugin Compose.
3. Clonar el repo y crear el `.env` de producción.
4. `docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build`.
5. Verificar HTTPS, subida de imágenes, correo real, mapa y alta de negocios.
6. Reiniciar el host y comprobar que la VM y los contenedores arrancan solos.
7. Probar la restauración de un backup (base de datos y `uploads`).
## Solución de problemas
 
| Síntoma | Causa y solución |
|---|---|
| WordPress no puede instalar plugins o subir imágenes | Permisos del volumen. Ejecuta `docker compose exec -u root wordpress chown -R www-data:www-data /var/www/html/wp-content` |
| `bad interpreter` al ejecutar un `.sh` | El archivo tiene CRLF. Conviértelo a LF |
| `db` no pasa a `healthy` | Primer arranque lento; espera o revisa `docker compose logs db` |
| Puerto ocupado | Cambia `WP_PORT`, `MAIL_PORT` o `DB_PORT` en `.env` |
| Desarrollo lento en Windows | Activa VirtioFS en Docker Desktop, excluye la carpeta del antivirus y mantén una ruta corta (`C:\dev\directorio-wp`) |
| Cambiaste la versión de MySQL y falla | El volumen conserva datos de la versión anterior. En desarrollo: `docker compose down -v` (borra datos) |
