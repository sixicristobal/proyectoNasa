# INSTRUCCION.md — Field Shift sin n8n: solo Laravel + Ollama (Docker por Tailscale)

> Actualizado: 2026-10-09 — migración n8n → 100% local.
> **v2 (2026-10-09 tarde):** backfill 30 días, 7 parámetros NASA (máx/mín, viento, solar),
> alertas lluvia/tormenta + antidup, dashboard del mes, visor 🛰️ `/imagenes` (GIBS).
> Estado verificado: `php artisan test` **19/19 verde**, dashboard 200, `/imagenes` 200,
> alerta real `lluvia_fuerte 2026-09-29 (36.3mm)` generada.

---

## 1. Arquitectura nueva (sin n8n)

```
[NASA POWER API] ──Http facade──> [nasa:sync] ──upsert──> [MariaDB nasa_agro_data]
[Earthdata MOD13Q1] ──> HDF ──> [extract_ndvi.py] ──> [misma tabla, col ndvi_value]

[Scheduler Laravel] ──daily 03:00──> nasa:sync ──daily 03:30──> agro:check-alerts
                                                              ├─ reglas locales (sequía/helada/estrés)
                                                              ├─ Ollama redacta mensaje
                                                              └─ Alert + notify(User)

[ChatController] ──lee MariaDB 7 días + $systemPrompt──> [Ollama Docker Tailscale] ──> respuesta
[RecommendationController] ──topic + contexto──> [LlmService → OllamaService] ──> sesión
[ReforestationController] ──catálogo 6 especies + clima──> [OllamaService] ──> sesión
[Dashboard] ──lee nasa_agro_data + alerts──> Blade + Chart.js
```

**Qué se eliminó:**
- `app/Http/Controllers/Api/N8nAlertController.php` (borrado)
- `N8N_WEBHOOK_URL`, `GEMINI_API_KEY`, `DEEPSEEK_API_KEY`, `LLM_DRIVER=n8n` (fuera de `.env`)
- `POST /api/n8n/*` ahora responde `410 Gone` (compatibilidad para workflows viejos)
- `LlmService::viaGemini/viaDeepSeek/viaN8n` (reemplazado por delegación a Ollama)

**Qué se agregó:**
- `app/Console/Commands/CheckAgroAlerts.php` (`agro:check-alerts`)
- Scheduler en `routes/console.php` (03:00 sync, 03:30 alerts, `America/Asuncion`)
- Tests: `AgroModulesTest` (Ollama fake `*11434*`) + `LocalAlertCommandTest`

---

## 2. Configuración `.env` (la única válida ahora)

```env
OLLAMA_HOST=http://100.107.189.14:11434
OLLAMA_MODEL=llama3.2:3b
LLM_DRIVER=ollama
```

Verificar:

```bash
grep -E "OLLAMA|LLM_DRIVER" .env
curl -m 10 http://100.107.189.14:11434/api/tags
# Esperado: {"models":[{"name":"llama3.2:3b",...}]}
tailscale status | grep 100.107.189.14
docker ps | grep ollama   # en tu servidor casero
docker exec <contenedor> ollama list
```

Si `curl` falla: Tailscale caído en este servidor o en el casero, o el contenedor no expone `11434`. No hay fallback a Gemini — es Ollama o error 502 con mensaje amigable.

---

## 3. Flujo paso a paso para verificar

### PASO 1 — Ingesta (el Cron de Laravel, reemplaza n8n)

```bash
# Manual:
php artisan nasa:sync --lat=-25.2637 --lon=-57.5759 --days=7
php artisan tinker --execute="echo App\Models\NasaAgroData::count();"

# Scheduler (ver qué hay programado):
php artisan schedule:list
# Esperado:
#  0  6 * * * nasa:sync ...        (03:00 Asunción = 06:00 UTC)
#  30 6 * * * agro:check-alerts ...

# Probar el scheduler sin esperar a mañana:
php artisan schedule:run
```

**Cron del sistema (OBLIGATORIO, una sola vez):**

```bash
crontab -e
# agregar:
* * * * * cd /var/www/html/ProyectoNASA && php artisan schedule:run >> /dev/null 2>&1
```

Sin esa línea, `schedule:list` muestra los jobs pero nunca se ejecutan.

Internamente `NasaPowerService::fetchDaily` hace:

```php
Http::timeout(60)->get('https://power.larc.nasa.gov/api/temporal/daily/point', [
  'parameters' => 'T2M,PRECTOTCORR,RH2M', 'community' => 'AG', ...
]);
// luego NasaAgroData::upsertRows() por clave [lat,lon,date] (idempotente)
```

### PASO 2 — Inferencia (ChatController sin n8n)

Ejemplo real de cómo queda (ya aplicado en `app/Http/Controllers/ChatController.php`):

```php
$context = /* últimos 7 de MariaDB: "Fecha ... temp ... lluvia ... NDVI ..." */;
$messages = array_merge(
  [['role' => 'system', 'content' => $this->systemPrompt."\n\nDatos recientes:\n".$context]],
  array_slice($history, -10)
);
$reply = $ollama->chat($messages); // POST http://100.107.189.14:11434/api/chat
```

Probar real (sin fake, contra tu Docker):

```bash
php artisan tinker --execute="echo (new App\Services\OllamaService)->chat([['role'=>'user','content'=>'Responde solo: OK']]);"
# Verificado 2026-10-09: responde (lento, ~30-60s por Tailscale + 3B en CPU — normal)
```

Si tarda >180s: sube `timeout` en `OllamaService.php` o baja `num_predict` (actual 400). Si da 404 `model not found`: el nombre exacto es `llama3.2:3b` (con `:3b`), no `llama3.2`.

### PASO 3 — Recomendaciones y Reforestación (también Ollama)

- `RecommendationController` → `LlmService::recommend($topic,$context)` → `OllamaService::chat()` (mismo endpoint, prompt de ingeniero agrónomo).
- `ReforestationController` → catálogo 6 nativas + clima → `OllamaService::chat()`.
- Para cambiar especies: edita `$species` en `ReforestationController.php:13-20`.

```bash
php artisan test --filter=AgroModulesTest
```

### PASO 4 — Alertas locales (reemplaza n8n Code+Gemini+POST)

```bash
php artisan agro:check-alerts
php artisan agro:check-alerts --lat=-25.2637 --lon=-57.5759
php artisan tinker --execute="echo App\Models\Alert::latest()->first()?->toJson();"
```

Reglas en `CheckAgroAlerts.php`:
- `sequia` si precip media 7d < 1.0 mm/día
- `helada` si temp mín < 5 °C
- `estres_hidrico` si humedad media < 40%

Cada riesgo → Ollama redacta 3-4 líneas → `Alert::create(source=laravel-ollama)` → `notify()` al primer `User` (mail+db). Si Ollama apagado, igual guarda la alerta con el detalle técnico + motivo. Ajusta umbrales ahí mismo.

### PASO 5 — Dashboard `/`

```bash
curl -s http://localhost:8000/ | grep -o "ndviChart\|Sin registros"
```

Lee `nasa_agro_data` (365 max, `scopeNear` ±0.05°) + últimas 5 `alerts`. Etiqueta ahora: “Últimas alertas (Laravel + Ollama)”.

### PASO 6 — Suite completa

```bash
php artisan test
# 2026-10-09: 12 tests, 12 pasan
```

---

## 4. Checklist para cambiar cosas

- [ ] Cambiar coordenadas por defecto: `routes/console.php` (`--lat/--lon`) + `SyncNasaPowerData.php` defaults.
- [ ] Cambiar horarios: `dailyAt('03:00')` en `routes/console.php` (hora Asunción).
- [ ] Cambiar modelo: `OLLAMA_MODEL` en `.env` (verifica con `/api/tags` que exista exacto).
- [ ] Cambiar umbrales de alerta: `CheckAgroAlerts.php` (`< 1.0`, `< 5.0`, `< 40`).
- [ ] Cambiar prompts: `$systemPrompt` en `ChatController`, prompt en `LlmService::recommend`, prompt catálogo en `ReforestationController`.
- [ ] E-mail real: `MAIL_*` en `.env` + `php artisan queue:work` (las notificaciones son Queueable).
- [ ] NDVI real: `pip install rasterio` + credenciales `NASA_EARTHDATA_*`. Sin esto, `ndvi_value=null` (degradación aceptada).

---

## 5. Troubleshooting

| Síntoma | Causa probable | Fix |
|---|---|---|
| `No se pudo conectar con Ollama en http://100.107.189.14:11434` | Tailscale down o Docker parado | `tailscale status` en ambos, `docker ps`, `curl /api/tags` |
| `modelo ... no está en tu Docker` (404) | `OLLAMA_MODEL` sin tag | Usa `llama3.2:3b` exacto, `ollama pull` |
| Respuesta muy lenta (60s+) | 3B en CPU vía Tailscale | Normal; baja `num_predict` a 250 o usa máquina con GPU |
| `Sin registros` en dashboard | Nunca corrió sync / cron del sistema falta | `nasa:sync` manual + agrega `schedule:run` a crontab |
| `Sin riesgos detectados` siempre | Umbrales muy bajos para tu zona | Ajusta en `CheckAgroAlerts.php` |
| Viejo n8n sigue llamando | Workflow olvidado | Recibe `410 Gone` con mensaje; apaga el workflow en n8n |

---

## 6. v2 — Flujo completo mensual + lluvias/tormentas + imágenes (2026-10-09)

### Qué cambió
- **Migración** `2026_10_09_000001`: columnas `temp_max, temp_min, wind_speed (m/s), solar_radiation` (todas nullable).
- **`NasaPowerService`**: pide `T2M,T2M_MAX,T2M_MIN,PRECTOTCORR,RH2M,WS2M,ALLSKY_SFC_SW_DWN` (7 params, límite API 20).
- **Backfill**: `nasa:sync --days=30` → 30 filas (2026-09-10..2026-10-09), 26 con dato (4 NULL por lag NRT normal).
- **Scheduler**: `nasa:sync` ahora con `--days=10` (rellena el hueco del lag).
- **`agro:check-alerts --days=10`**: ventana 10 días con dato; reglas nuevas:
  - `lluvia_fuerte` ≥20mm/día · `lluvia_extrema` ≥50mm · `tormenta_probable` (≥10mm + viento ≥40km/h + humedad ≥80%) · `inundacion_encharcamiento` (acumulado 3d ≥60mm) · `helada` con `temp_min` <3°C (fallback temp <5°C) · sequía/estrés igual.
  - **Antidup**: no repite `risk_type + data_date`.
  - `detectRisks()` es público para testear sin Ollama: `tinker --execute="(new CheckAgroAlerts)->detectRisks(...)"`.
- **Dashboard `/`**: KPIs lluvia acumulada 30d + días con lluvia + viento km/h (con máx/mín); gráfico barras `precipChart` 30 días; lista eventos ≥10mm con severidad; alertas con badges por tipo (8 últimas).
- **Contextos IA** (chat/recomendaciones/reforestación): incluyen máx/mín + viento km/h (null-safe `s/d`).
- **Nuevo `/imagenes` (NASA GIBS, sin key)** — `NasaImageryService` + `ImageryController` + Leaflet:
  - Capas: color verdadero Terra, falso color 7-2-1 (vegetación verde / agua oscura → inundaciones), VIIRS.
  - Tiles: `gibs.earthdata.nasa.gov/wmts/epsg3857/best/.../GoogleMapsCompatible_Level9/{z}/{y}/{x}.jpg` (maxZoom 9).
  - Selector fecha (clamp: no futura, ≥2000-02-24) + lat/lon parcela con marcador + PNG Worldview + link Worldview oficial.
  - Fecha por defecto: hoy −4 días (lag NRT del mosaico).

### Comandos v2
```bash
php artisan migrate --force
php artisan nasa:sync --lat=-25.2637 --lon=-57.5759 --days=30
php artisan agro:check-alerts --days=30   # 1 llamada Ollama por riesgo nuevo
php artisan test                          # 19/19
curl -s http://localhost:8000/ | grep -o "precipChart"              # dashboard mes
curl -s "http://localhost:8000/imagenes" | grep -o "gibs" | head -1 # visor
```

### Si algo falla (v2)
| Síntoma | Fix |
|---|---|
| `nasa:sync` 422 de NASA | Un param nuevo no disponible ese día: revisa `storage/logs/laravel.log`, el upsert deja lo anterior intacto |
| `agro:check-alerts` crea helada fantasma | Solo si `temp_min` NULL y `temp` <5 (fallback); con backfill nuevo no pasa |
| Tile GIBS gris/con huecos | Fecha muy reciente o con nubes → retrocede 1–2 días en el selector |
| `/imagenes` fecha futura | Se fija sola a hoy −4 (normalizeDate) |

### v2.1 — Botón "Actualizar datos" (manual)
- **Ruta**: `POST /actualizar` (`DataRefreshController@store`, con CSRF) → corre `nasa:sync --days=10`
  en la request y lanza `agro:check-alerts` en segundo plano (log: `storage/logs/agro-alerts-manual.log`).
- **Por qué así**: el sync es rápido (segundos); el check llama a Ollama por cada riesgo nuevo y puede
  tardar minutos → va desacoplado con `exec()` para no colgar la página. Al terminar el sync, el
  chat/recomendaciones ya usan contexto fresco (leen la BD en vivo).
- **Dashboard**: botón amarillo "🔄 Actualizar datos" (conserva lat/lon del filtro) + mensaje verde/rojo
  con el resultado. En tests el proceso en segundo plano no se lanza (`runningUnitTests`).
- Verificación: `php artisan test` 23/23 (incluye `ManualRefreshTest` con NASA fakeada).

### Caso real 2026-10-09: "Ollama no disponible" en alerta lluvia_fuerte 29-sep
- **Causa raíz (verificada)**: el modelo NO estaba cargado (`/api/ps → []`), el servidor casero
  infiere en CPU a ~7 tok/s y encima el daemon se colgó progresivamente (tags OK → version timeout).
  No es la app: red Tailscale sana (tags en 36ms cuando el daemon respondía).
- **Mitigaciones aplicadas en código**:
  - `OllamaService::chat($messages, $timeout=180)` + `'keep_alive' => '24h'` (evita carga en frío de ~2GB).
  - `agro:check-alerts` usa timeout 300 + fallback corto visible ("Dictamen IA pendiente...")
    y el diagnóstico largo va a `Log::warning`, no a la pantalla.
- **Qué revisar en el servidor casero** (por SSH o local):
  `docker ps | grep ollama` · `docker stats --no-stream` (¿CPU/RAM al 100%?) ·
  `free -h` (¿swap lleno?) · `docker logs ollama --tail 50` (¿OOM "out of memory"?) ·
  `dmesg | grep -i -E "oom|killed"` · `tailscale status` · `uptime`.
- **Fix recomendado servidor**: `OLLAMA_KEEP_ALIVE=24h` en el compose + reiniciar contenedor;
  si tiene poca RAM, cambiar a `llama3.2:1b` (`OLLAMA_MODEL` en `.env` Laravel) o agregar swap.
  Regenerar el mensaje de la alerta con `agro:check-alerts` una vez sano (antidup omite las
  existentes: borrar la alerta vieja primero si se quiere el dictamen nuevo).
