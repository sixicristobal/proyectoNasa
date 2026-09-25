# INSTRUCTIVO DE PROYECTO: "Field Shift: Adapting Farms with NASA Data"

## 1. Contexto y Reglas Base
*   **Rol de la IA:** Eres un Desarrollador Senior Full-Stack. Tu tarea es construir los módulos backend, frontend y flujos automatizados para este desafío de NASA Space Apps 2026.
*   **Regla Estricta:** Lee y aplica siempre mi archivo `agents.md` para las reglas de código. Genera código conciso, sin explicaciones redundantes.
*   **Stack Tecnológico:** Laravel (PHP 8.3), Mariadb (10.11.8-MariaDB), Bootstrap 5, Chart.js, Python 3 (Rasterio), n8n, Ollama (Llama 3.2 local), API de Gemini.

## 2. Arquitectura de Base de Datos (Mariadb 10.11.8-MariaDB)
*   **Diseño:** Evitar JSON crudo. Usar tablas normalizadas.
*   **Migración principal (`nasa_agro_data`):**
    *   Columnas: `lat` (decimal), `lon` (decimal), `date` (date), `temp` (float), `precip` (float), `humidity` (float), `ndvi_value` (float).
    *   Índice compuesto: `unique(['lat', 'lon', 'date'])`.
    *   Operación de guardado: Usar SIEMPRE `Model::upsert()` para inserciones masivas eficientes.

## 3. Integración de APIs de la NASA (Servicios Laravel)
Crea clases en `app/Services/` usando el Facade `Http`:
*   **NASA POWER (`NasaPowerService`):** Endpoint `GET https://power.larc.nasa.gov/api/temporal/daily/point`. Parámetros: `T2M, PRECTOTCORR, RH2M`. 
*   **CMR Earthdata (`EarthdataService`):**
    *   Búsqueda: `GET https://cmr.earthdata.nasa.gov/search/granules.json` (Producto MOD13Q1 para NDVI).
    *   Descarga HDF/TIF: Requiere `Http::withBasicAuth()` usando credenciales `.env` y habilitar redirecciones con `CookieJar` de Guzzle. Archivos guardados en `storage/app/nasa/`.

## 4. Procesamiento Python (Extracción NDVI)
*   **Script (`storage/scripts/extract_ndvi.py`):** Usar la librería `rasterio` para abrir el HDF/TIF, buscar las coordenadas (lat/lon) y extraer el valor del píxel (banda 1 * 0.0001). Imprimir resultado en JSON.
*   **Ejecución desde PHP:** Crear `NdviProcessor` que ejecute el script usando `Illuminate\Support\Facades\Process` y guarde el `ndvi_value` devuelto en MySQL.

## 5. Integración IA local (Ollama - Llama 3.2)
*   **Servicio (`OllamaService`):**
    *   Endpoint: `POST http://127.0.0.1:11434/api/chat`.
    *   Modelo: `llama3.2`.
    *   Opciones críticas: `['temperature' => 0.2, 'num_ctx' => 4096, 'num_predict' => 400]`.
*   **Memoria (RAG):** El controlador debe gestionar el historial en `session('chat_history')`, inyectando el system prompt (experto agroecológico en Paraguay) y formateando el histórico de `nasa_agro_data` como texto plano (no JSON) dentro del prompt.

## 6. Automatización de Alertas (n8n + Gemini)
*   **Flujo n8n:**
    1.  *Trigger:* Cron diario.
    2.  *MySQL Node:* `SELECT date, temp, precip, humidity FROM nasa_agro_data ORDER BY date DESC LIMIT 7`.
    3.  *Code Node:* Formatear resultado a string.
    4.  *Gemini Node:* Analizar riesgo de sequía/heladas.
    5.  *HTTP Node:* Enviar `POST` Webhook a la API de Laravel (`/api/n8n/alerts`).
*   **Receptor Laravel:** Controlador que recibe el webhook, guarda la alerta y usa `Illuminate\Notifications` para notificar al usuario.

## 7. Frontend (Bootstrap 5)
*   **Dashboard NDVI:** Vista Blade que consume el modelo `NasaAgroData`. Implementar `Chart.js` para graficar la curva de salud hídrica (fechas en eje X, valores NDVI en eje Y).