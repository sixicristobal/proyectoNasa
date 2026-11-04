# Instalación — DSS Agrícola NASA

## Requisitos
- PHP >= 8.2, Composer, Node.js, MariaDB/MySQL.

## Pasos
1. `composer install`
2. `cp .env.example .env && php artisan key:generate`
3. Configurar DB en `.env` (DB_DATABASE, DB_USERNAME, DB_PASSWORD).
4. `php artisan migrate --seed`
5. `npm install && npm run build`
6. `php artisan storage:link`
7. `php artisan serve`

## Variables .env NASA
- `EARTHDATA_USER`, `EARTHDATA_PASS`, `EARTHDATA_TOKEN` (para SMAP/GPM/ECOSTRESS).
- `OLLAMA_*` / `GEMINI_API_KEY` para IA.

## Comandos artisan disponibles
- `php artisan nasa:sync [--lat=] [--lon=] [--days=]` — NASA POWER (T2M, precip, RH, viento, solar).
- `php artisan agro:check-alerts` — alertas + dictamen IA.
- `php artisan queue:work` — procesa jobs en cola.
- `php artisan schedule:run` — ejecuta tareas programadas (configurar cron: `* * * * * php artisan schedule:run`).
- `php artisan migrate` / `migrate:fresh --seed`.
- `php artisan test` — ejecuta suite (Pest/PHPUnit).

## Verificación
- `php artisan test` → 59 tests pasando.
- `php artisan route:list` → rutas dashboard, imágenes, chat, recomendaciones, rotaciones, reforestación.
" filePath="/var/www/html/ProyectoNasa/ProyectoNASA/INSTALACION.md