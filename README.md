![Logo ProyectoNASA](https://assets.spaceappschallenge.org/media/images/logoterereJape.2e16d0ba.fill-300x250.png)

# Field Shift: Adapting Farms with NASA Data

**"Empowering local farmers in Paraguay to fight climate change through regenerative agriculture, powered by NASA's Earth observation data."**

## Authors

This project was developed by students from **Universidad Evangélica del Paraguay (UEP)**:

- Sixinio Cristóbal
- Bruno Duran
- Ever Vega
- Juan Gutierrez
- Dahiana Cardozo
- Elias Vera

## Table of Contents

- [The Problem](#the-problem-why-field-shift)
- [The Solution](#the-solution)
- [How We Used NASA Data](#how-we-used-nasa-data)
- [AI Integration](#ai-integration-for-actionable-insights)
- [Features](#features)
- [How the Pipeline Works](#how-the-pipeline-works)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Scheduled Jobs and Commands](#scheduled-jobs-and-commands)
- [Project Structure](#project-structure)
- [Main Routes](#main-routes)
- [Data Model](#data-model)
- [Known Limitations](#known-limitations)
- [Technologies Used](#technologies-used)
- [Testing](#testing)
- [License](#license)

## The Problem: Why Field Shift?

In regions like Paraguay, small and medium-scale farmers face unpredictable climate shifts, soil degradation, and water scarcity. Traditional farming relies on historical intuition, which is no longer effective against rapid climate change.

We discovered that farmers lack access to real-time, actionable environmental data. While NASA provides incredible satellite observations, the raw data is too complex for an average farmer to interpret. They need a bridge between high-level satellite data and daily field decisions.

## The Solution

Field Shift is a Laravel web platform that acts as an intelligent translator. It fetches raw satellite and meteorological data (precipitation, soil moisture, surface temperature, vegetation) and turns it into a dashboard, alerts and recommendations for regenerative agriculture.

The system mixes two very different kinds of "intelligence", and the README is explicit about which is which:

- **Deterministic, rule-based logic** for alerts, rainfall/temperature risk detection and crop-rotation ranking. These results are reproducible and do not need an LLM.
- **LLMs** for conversational agronomy advice (chat, planting/irrigation recommendations, reforestation planning) and for writing the narrative of each alert.

### Who Benefits?

1. **Local Farmers:** They get alerts, rotation plans and practical advice for their plots without needing a degree in data science.
2. **Agricultural Consultants (Agroecologists):** They can compare fields, review satellite imagery and browse raw NASA granules.
3. **The Environment:** By promoting regenerative practices (optimized crop rotation and reforestation), the system helps restore soil health and sequester carbon.

## How We Used NASA Data

Rather than calling one API per dataset, most satellite products are discovered through the same **NASA CMR** catalog and then downloaded from Earthdata:

| Source | Endpoint | Product used | What we do with it |
| --- | --- | --- | --- |
| **NASA POWER** | `https://power.larc.nasa.gov/api/temporal/daily/point` | `T2M`, `T2M_MAX`, `T2M_MIN`, `PRECTOTCORR`, `RH2M`, `WS2M`, `ALLSKY_SFC_SW_DWN` (community `AG`) | Daily temperature, rainfall, humidity, wind and solar radiation per field. Public, no token. |
| **GPM (IMERG)** | `https://cmr.earthdata.nasa.gov/search/granules.json` + HDF5 download | `GPM_3IMERGDF` v07 | Daily precipitation per field; derived metrics: maximum daily rainfall, heavy-rain days (>50 mm) and longest dry spell. Extracted with `storage/scripts/extract_gpm_precip.py`. |
| **SMAP** | `https://appeears.earthdatacloud.nasa.gov/api` (`/task`, `/task/{id}`, `/bundle/{id}`) | `SPL3SMP_E.006`, layer `soil_moisture_am` | Point-sampled surface soil moisture (m³/m³) over 90 days. Used by the rotation engine's water balance. Falls back to CMR metadata (`SPL3SMP_E` v061) when no Earthdata token is configured, storing the granule with a `null` value instead of failing. |
| **Landsat** | CMR granules | `HLSL30` (Harmonized Landsat/Sentinel-2) | Reflectance granules browsable and downloadable from *Advanced NASA Data*. |
| **MODIS** | CMR granules | `MOD11A2` (land surface temperature), `MOD16A2` (evapotranspiration) | Surface temperature and water-use context per field, refreshed daily into `observations`. |
| **ECOSTRESS** | CMR granules | `ECO_L2T_LSTE` | High-resolution land surface temperature granules. |
| **SRTM** | CMR granules (no temporal filter, static dataset) | `SRTMGL1` | Topographical context for the field. |
| **GIBS / Worldview** | `https://gibs.earthdata.nasa.gov/wmts/epsg3857/best/...` and `https://wvs.earthdata.nasa.gov/api/v1/snapshot` | MODIS Terra true/false color, VIIRS, SMAP soil moisture, IMERG precipitation | Public map tiles, snapshots and Worldview links for the imagery view. No token required. |

**NDVI:** the `nasa_agro_data.ndvi_value` column, `NdviProcessor` and `storage/scripts/extract_ndvi.py` are implemented (MOD13Q1 via `EarthdataService`), and the dashboard renders NDVI whenever a value exists. However, NDVI extraction is **not part of the scheduler yet**: rows are stored with `ndvi_value = NULL` until that pipeline is wired or values are inserted manually. The AI prompts and rotation engine already read the column and degrade gracefully when it is `NULL`.

Fields are always resolved by their `latitude`/`longitude` (with a ±0.05° tolerance), so the same code works for the default Asunción coordinates and for any user-created field.

## AI Integration for Actionable Insights

`LlmService` supports two interchangeable drivers:

- **Gemini** (`https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent`) via `GEMINI_API_KEY`.
- **Ollama** (`OLLAMA_HOST`) for a self-hosted model over Tailscale, with a long timeout because CPU inference is slow.

The driver is selected with `LLM_DRIVER` (`gemini` or `ollama`). AI is used in four places:

1. **Chat (`/chat`):** the last 5 NASA POWER rows become system context; the conversation keeps the last 20 messages in the session.
2. **Recommendations (`/recomendaciones`):** generates planting, irrigation or rotation advice from recent climate data.
3. **Reforestation (`/reforestacion`):** recommends 2–3 species from a fixed catalog of six native Paraguayan species, filtered by soil type and recent climate.
4. **Alert narrative (`agro:check-alerts`):** turns each detected risk into a short weekly action. If the LLM is unavailable, the alert is still saved with a neutral "AI pending" message, so monitoring never stops.

The **crop-rotation ranking is not LLM output**: `RotationEngine` scores strategies with agronomic rules and the farmer's 0–5 priorities. This is intentional — the result is auditable, and every recommendation stores `why_suggested`, `evidence`, `benefits`, `risks`, `information_gaps`, `confidence` and `requires_local_validation`.

## Features

- **Dashboard (`/`):** NASA POWER series for a coordinate, date-range filters (7/30/90 days or custom), rainfall KPIs (accumulated rainfall, rainy days, largest events), current status, and the latest alerts.
- **Manual refresh:** runs `nasa:sync` inline and launches `agro:check-alerts` in the background so the page does not block on the LLM.
- **Satellite imagery (`/imagenes`):** Leaflet + NASA GIBS WMTS layers (MODIS Terra true/false color, VIIRS, SMAP soil moisture, IMERG precipitation), date picker, static snapshot and Worldview links.
- **Advanced NASA data (`/datos-avanzados`):** browses CMR granules for ECOSTRESS, SRTM, MODIS LST, MODIS ET and Landsat; downloads go through the server's Earthdata credentials using **temporary signed URLs**, so the route cannot be used as an open proxy.
- **Crop rotation comparator (`/rotaciones`):** ranks at least 3 rotation strategies (current/baseline, water-saving, soil-health, income) for a field, using SMAP/GPM/POWER/NDVI context plus farmer priorities; supports creating new fields with soil and irrigation data.
- **AI recommendations (`/recomendaciones`):** planting, irrigation and rotation advice in Spanish or English.
- **Reforestation planner (`/reforestacion`):** native-species suggestions with planting season and care notes.
- **AI chat (`/chat`):** conversational agroecology assistant grounded on recent NASA POWER data.
- **Alerts:** deterministic detection of extreme/heavy rain, probable storms, 3-day waterlogging, drought, frost and water stress, deduplicated per risk type and date, delivered as in-app + email notifications.
- **Bilingual UI:** Spanish and English (`lang/es`, `lang/en`, `/lang/{locale}`).

## How the Pipeline Works

```
                    ┌──────────────────────────────┐
NASA POWER ────────►│ nasa:sync / POST /actualizar │──► nasa_agro_data (upsert)
                    └──────────────────────────────┘
                                  │
                                  ▼
                    ┌──────────────────────────────┐
                    │ agro:check-alerts (rules)    │──► alerts ──► mail + database
                    │        + LLM narrative       │
                    └──────────────────────────────┘

Fields ──► FetchNasaObservationsJob ──► FetchSmapJob / FetchGpmJob ──► observations
                                              │
ECOSTRESS / SRTM / MODIS / Landsat jobs ──────┘
                                              │
                                              ▼
                                  GenerateRecommendationsJob
                                  (RotationEngine + weights)
                                              │
                                              ▼
                              rotations / rotation_steps / recommendations
                                              │
                                              ▼
                          Dashboard, /rotaciones, /recomendaciones, /chat
```

All NASA values are parsed and normalized (POWER's `-999` fill values become `NULL`) and stored as JSON/numeric columns in MariaDB for fast querying, offline analysis and AI context.

## Requirements

- PHP 8.3 or higher (the provided Docker image uses PHP 8.5)
- Composer
- Node.js 18 or higher and npm
- MariaDB 11 / MySQL 8+ (SQLite is supported and used by the test suite)
- Python 3 with `numpy` and `h5py` for GPM/NDVI HDF extraction
- Git (optional)
- An Earthdata account/token for GPM, SMAP, Landsat, MODIS, ECOSTRESS and SRTM downloads
- Optional: Docker and Docker Compose

## Installation

### Option A — Docker Compose (recommended)

The repository includes MariaDB, the web app, a scheduler and a queue worker, plus a `test` profile.

```bash
git clone https://github.com/sixicristobal/proyectoNasa.git
cd proyectoNasa
cp .env.example .env
# Set DB_CONNECTION=mysql, DB_HOST=mariadb, DB_DATABASE, DB_USERNAME, DB_PASSWORD
docker compose up -d --build
```

The app entrypoint waits for MariaDB, runs `php artisan migrate --force` and then serves on `http://localhost:8000` (bound to `APP_BIND_IP`, `127.0.0.1` by default). The `scheduler` container runs `schedule:work` and the `queue` container runs `queue:work --timeout=1900`.

```bash
docker compose --profile test run --rm test   # PHPUnit suite
```

### Option B — Local

```bash
composer install
cp .env.example .env
php artisan key:generate
# Configure DB_* in .env
php artisan migrate --seed
npm install && npm run build
php artisan storage:link
php artisan serve
```

Then run the two background processes in separate terminals:

```bash
php artisan queue:work --sleep=3 --tries=3 --timeout=1900
php artisan schedule:work        # or a cron: * * * * * php artisan schedule:run
```

`migrate --seed` creates the crop catalog and a demo user. There is no login screen: the app is demo-first, and notifications go to the first user in the database.

## Configuration

Key variables from `.env` (see `.env.example`):

| Variable | Purpose |
| --- | --- |
| `APP_NAME`, `APP_URL`, `APP_PORT`, `APP_BIND_IP` | App identity and serve binding. |
| `APP_LOCALE` / `APP_FALLBACK_LOCALE` | Default UI language (`en` or `es`). |
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Database connection. |
| `QUEUE_CONNECTION` | `database` in the provided setup; required for the NASA jobs. |
| `NASA_EARTHDATA_USERNAME`, `NASA_EARTHDATA_PASSWORD`, `NASA_EARTHDATA_TOKEN` | Earthdata credentials. The token is preferred; aliases `EARTHDATA_USER`, `EARTHDATA_PASS`, `EARTHDATA_TOKEN` are also accepted. |
| `CMR_SEARCH_URL` | CMR granules endpoint (default `https://cmr.earthdata.nasa.gov/search/granules.json`). |
| `APPEARS_*` | AppEEARS base URL, SMAP product/layer/version, timeout and poll interval. |
| `GPM_SHORT_NAME`, `GPM_VERSION`, `GPM_PAGE_SIZE` | GPM IMERG collection settings. |
| `LLM_DRIVER` | `gemini` or `ollama`. |
| `GEMINI_API_KEY`, `GEMINI_MODEL`, `GEMINI_TIMEOUT` | Gemini driver. |
| `OLLAMA_HOST`, `OLLAMA_MODEL`, `OLLAMA_TIMEOUT`, `OLLAMA_NUM_PREDICT` | Ollama driver. |
| `MAIL_*` | Alert emails (defaults to `log`). |

Each Earthdata product must have its **EULA accepted in Earthdata Login** for the configured account; otherwise NASA returns 401/403 and the UI shows the "download unauthorized" message.

## Scheduled Jobs and Commands

All schedules run in `America/Asuncion`:

| Time | Task | What it does |
| --- | --- | --- |
| 03:00 | `nasa:sync --days=10` | NASA POWER → `nasa_agro_data`. |
| 03:00 | `FetchNasaObservationsJob` | Queues `FetchSmapJob` (90 days) and `FetchGpmJob` (30 days) for every field; results cached 24 h. |
| 03:15 | `ObtenerDatosEcostressJob` | ECOSTRESS granules (last 30 days). |
| 03:20 | `ObtenerDatosSrtmJob` | SRTM granules. |
| 03:25 | `ObtenerDatosModisExtendidoJob` | MODIS LST + ET granules (last 30 days). |
| 03:30 | `agro:check-alerts` | Rule-based risks + AI narrative → `alerts` + notifications. |
| 03:35 | `ObtenerDatosLandsatJob` | HLS Landsat granules (last 30 days). |
| Monday 04:30 | `GenerateRecommendationsJob` | Recalculates rotations and recommendations for every field. |

Artisan commands:

- `php artisan nasa:sync [--lat=] [--lon=] [--days=] [--start=] [--end=]` — NASA POWER sync, max 366 days per call.
- `php artisan agro:check-alerts [--lat=] [--lon=] [--days=] [--locale=]` — risk detection and alert generation.
- `php artisan queue:work` / `php artisan schedule:work` — workers required by the pipeline.
- `php artisan migrate`, `migrate:fresh --seed`, `php artisan test`.

## Project Structure

```
app/
├── Console/Commands/        SyncNasaPowerData, CheckAgroAlerts
├── Http/Controllers/        Dashboard, DataRefresh, Imagery, AdvancedData,
│                            Recommendation, Reforestation, RotationComparison, Chat
├── Jobs/                    FetchNasaObservations, FetchSmap, FetchGpm,
│                            GenerateRecommendations, ObtenerDatos{Ecostress,Srtm,ModisExtendido,Landsat}
├── Models/                  NasaAgroData, Alert, Observation, Farm, Field, Crop,
│                            FarmerPriority, Rotation, RotationStep, Recommendation, Feedback, User
├── Notifications/           RiskAlertNotification
└── Services/
    ├── NasaPowerService, NasaImageryService, EarthdataService, NdviProcessor
    ├── LlmService, GeminiService, OllamaService
    ├── RotationEngine
    └── Nasa/                CmrService, AppEarsService, GpmImergService,
                             SmapService, LandsatService, ModisExtendedService,
                             EcostressService, SrtmService
database/
├── migrations/              nasa_agro_data, alerts, farms, fields, crops,
│                            farmer_priorities, rotations, rotation_steps,
│                            observations, recommendations, feedback
└── seeders/                 CropSeeder, DatabaseSeeder
resources/views/             dashboard, imagery, advanced-data, chat,
                             recommendations, reforestation, rotations
storage/scripts/             extract_gpm_precip.py, extract_ndvi.py
docker/                      entrypoint.sh
compose.yaml                 app, scheduler, queue, mariadb, test
```

## Main Routes

| Method | URI | Name | Purpose |
| --- | --- | --- | --- |
| GET | `/` | `dashboard` | NASA POWER dashboard, filters and alerts. |
| GET | `/api/latest` | `dashboard.latest` | JSON with the latest NASA POWER record. |
| POST | `/actualizar` | `data.refresh` | Manual NASA POWER sync + background alerts. |
| GET | `/imagenes` | `imagery.index` | NASA GIBS imagery viewer. |
| GET | `/datos-avanzados` | `advanced.index` | CMR granule browser. |
| GET | `/datos-avanzados/descargar` | `advanced.download` | Signed-URL download via server credentials. |
| GET/POST | `/chat` | `chat.index`, `chat.store` | Agroecology assistant. |
| GET/POST | `/recomendaciones` | `recommendations.index`, `.store` | AI planting/irrigation/rotation advice. |
| GET/POST | `/reforestacion` | `reforestation.index`, `.store` | Native-species reforestation planner. |
| GET | `/rotaciones` | `rotations.index` | Rotation comparator per field. |
| POST | `/rotaciones` | `rotations.store` | Generate/refresh strategies for a field. |
| POST | `/rotaciones/parcela` | `rotations.fields.store` | Create a field (soil, pH, organic matter, irrigation). |
| GET | `/lang/{locale}` | `lang.switch` | Switch `es`/`en`. |

## Data Model

- `users` → `farms` → `fields`: a field stores coordinates, area, soil type, pH, organic matter and irrigation type, plus optional `farmer_priorities` weights.
- `nasa_agro_data`: daily NASA POWER record per coordinate (`temp`, `temp_max`, `temp_min`, `precip`, `humidity`, `wind_speed`, `solar_radiation`, `ndvi_value`), upserted by `(lat, lon, date)`.
- `observations`: per-field, per-source satellite values (`source`, `variable`, `value`, `unit`, `observed_at`, `raw_response`), upserted by `(field_id, source, variable, observed_at)`.
- `crops` + `rotations` + `rotation_steps`: catalog and rotation sequences (season, duration).
- `recommendations`: ranked strategies with evidence, benefits, risks, gaps, confidence and a local-validation flag.
- `alerts` + `notifications`: deduplicated risk alerts and their in-app/email delivery.
- `feedback`: reserved for farmer feedback on recommendations.

## Known Limitations

- **No authentication:** the app is a hackathon/demo build. Fields are shared and notifications target the first user.
- **NDVI ingestion is not scheduled** (see the note in *How We Used NASA Data*); the dashboard and AI handle missing values.
- **Near-real-time lag:** NASA POWER returns `NULL` for the most recent 3–5 days; the UI filters those rows out of the rainfall KPIs instead of showing zeros.
- **Earthdata EULA/token:** most satellite downloads fail without an authorized account, and AppEEARS tasks can take minutes.
- **LLM availability:** if Gemini/Ollama is down, chat and recommendations show an error, and alert narratives fall back to a neutral message.
- **Satellite data is metadata-first:** advanced jobs persist granule metadata and download links; values are extracted only for GPM (via Python/HDF5) and SMAP (via AppEEARS CSV).

## Technologies Used

- **Backend:** Laravel 13, PHP 8.3+; Laravel Sanctum and Tinker are installed.
- **Database:** MariaDB 11 / MySQL 8+ (SQLite for tests).
- **Frontend:** Blade, Bootstrap 5 (CDN), Chart.js, Leaflet, Tailwind (CDN in the advanced-data view); Vite 8 + Tailwind CSS 4 scaffolding is included for builds.
- **Data:** NASA POWER, CMR, AppEEARS, GIBS/Worldview; Python 3 (`numpy`, `h5py`) for HDF extraction.
- **AI:** Gemini API or Ollama (Llama 3.2).
- **Infrastructure:** Docker Compose (app, scheduler, queue, MariaDB), database queue and cache.

## Testing

```bash
php artisan test
# or
docker compose --profile test run --rm test
```

The suite (68 tests) covers NASA POWER sync and date ranges, NASA upserts, dashboard filters, imagery, advanced-data downloads, SMAP/GPM phase one, the rotation engine, alerts, manual refresh and both LLM drivers. External HTTP calls are faked with `Http::fake()`.

## License

MIT, as declared in `composer.json`.
