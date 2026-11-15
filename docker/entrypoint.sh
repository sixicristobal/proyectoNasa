#!/bin/sh
# Arranque del contenedor app:
# espera a que MariaDB acepte conexiones antes de migrar, para no entrar en
# bucle de reinicios si la base todavía no resolvió el DNS interno de Docker.
set -e

tries=40

while [ "$tries" -gt 0 ]; do
    if php artisan migrate --force; then
        exec php artisan serve --host=0.0.0.0 --port=8000 --no-reload
    fi

    tries=$((tries - 1))
    echo "MariaDB no disponible, reintentando en 3s (quedan $tries intentos)..."
    sleep 3
done

echo "No se pudo migrar tras 40 intentos. Revisá el contenedor nasa-mariadb."
exit 1
