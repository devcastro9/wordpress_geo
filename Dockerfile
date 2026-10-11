# ---------------------------------------------------------------
# Etapa 1: descarga plugins y temas de terceros con versión fija
# ---------------------------------------------------------------
FROM alpine:3.24.2 AS vendor
RUN apk add --no-cache curl unzip
WORKDIR /vendor
COPY scripts/vendor.lock /tmp/vendor.lock
RUN set -eu; \
    mkdir -p plugins themes; \
    grep -v '^\s*#' /tmp/vendor.lock | tr -d '\r' | while read -r type slug version; do \
      [ -z "${type:-}" ] && continue; \
      if [ "$version" = "latest" ]; then file="$slug.zip"; else file="$slug.$version.zip"; fi; \
      echo "Descargando $type $slug ($version)"; \
      curl -fsSL "https://downloads.wordpress.org/$type/$file" -o /tmp/pkg.zip; \
      unzip -q /tmp/pkg.zip -d "$type"s; \
      rm /tmp/pkg.zip; \
    done
 
# ---------------------------------------------------------------
# Etapa 2: imagen final
# ---------------------------------------------------------------
FROM wordpress:7.1-php8.3-apache

COPY uploads.ini /usr/local/etc/php/conf.d/uploads.ini

# Terceros (versiones fijadas en scripts/vendor.lock)
COPY --from=vendor /vendor/plugins/ /usr/src/wordpress/wp-content/plugins/
COPY --from=vendor /vendor/themes/  /usr/src/wordpress/wp-content/themes/

# Código propio
COPY wp-content/plugins/directorio-core  /usr/src/wordpress/wp-content/plugins/directorio-core
COPY wp-content/themes/directorio-child  /usr/src/wordpress/wp-content/themes/directorio-child

# Opcional: eliminar plugins de ejemplo que trae la imagen
RUN rm -rf /usr/src/wordpress/wp-content/plugins/hello.php \
           /usr/src/wordpress/wp-content/plugins/akismet
