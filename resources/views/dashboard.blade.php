<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('messages.field_shift_dashboard') }}</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('android-chrome-192x192.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>
        :root {
            --fs-green: #0d631b;
            --fs-green-soft: #bdefbe;
            --fs-cream: #fff8f6;
            --fs-sand: #ffe9e2;
            --fs-ink: #2b160f;
        }
        body { background: var(--fs-cream); color: var(--fs-ink); padding-bottom: 5rem; }
        @media (min-width: 992px) { body { padding-bottom: 0; } }
        .fs-header { background: rgba(255,248,246,.95); backdrop-filter: blur(12px); }
        .fs-main { padding-top: 5.5rem; }
        @media (min-width: 992px) { .fs-main { padding-top: 8rem; } }
        .fs-live-dot { width: .5rem; height: .5rem; }
        .fs-kpi { font-size: 2rem; font-weight: 700; line-height: 1; }
        .fs-kpi-sm { font-size: 1.2rem; font-weight: 700; line-height: 1.1; }
        .fs-card { border: 0; border-radius: 1rem; box-shadow: 0 1px 6px rgba(0,0,0,.06); }
        .fs-green { color: var(--fs-green); }
        .bg-fs-green { background: var(--fs-green); }
        .bg-fs-soft { background: var(--fs-green-soft); }
        .bg-fs-sand { background: var(--fs-sand); }
        .fs-nav a {
            color: var(--fs-ink); opacity: .72; text-decoration: none;
            font-size: .85rem; font-weight: 500; padding-bottom: .2rem;
        }
        .fs-nav a:hover, .fs-nav a.active { opacity: 1; color: var(--fs-green); }
        .fs-nav a.active { box-shadow: inset 0 -2px 0 var(--fs-green); }
        .fs-section-title {
            font-size: .78rem; font-weight: 600; text-transform: uppercase;
            letter-spacing: .06em; color: #6c757d; margin-bottom: .5rem;
        }
        .fs-summary { cursor: pointer; font-weight: 600; }
        .fs-summary::marker { color: var(--fs-green); }
        #alertas { scroll-margin-top: 6.5rem; }
        .fs-bottom-nav { height: 4rem; background: rgba(255,248,246,.95); backdrop-filter: blur(12px); }
        .fs-bottom-nav a { font-size: .65rem; text-transform: uppercase; letter-spacing: .04em; }
        .material-symbols-outlined { vertical-align: middle; }
    </style>
</head>
<body>

@include('partials.preloader')

{{-- Header fijo: identidad + navegación de escritorio --}}
<header class="fixed-top fs-header shadow-sm">
    <div class="container py-2">
        <div class="d-flex align-items-center justify-content-between gap-2">
            <div class="d-flex align-items-center gap-2">
                <span class="rounded-circle bg-fs-green text-white d-inline-flex align-items-center justify-content-center" style="width:2rem;height:2rem;">
                    <img src="{{ asset('img/logo.png') }}" alt="Field Shift" style="width:2rem;height:2rem;object-fit:contain;">
                </span>
                <div>
                    <div class="text-uppercase text-muted" style="font-size:.65rem;letter-spacing:.08em;">{{ __('messages.field_shift_dashboard') }}</div>
                    <strong>{{ __('messages.monitored_field') }}</strong>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-fs-soft text-success d-none d-sm-inline-block">
                    <span class="fs-live-dot rounded-circle bg-success d-inline-block" aria-hidden="true"></span>
                    NASA POWER · {{ $latest?->date->format('Y-m-d') ?? __('messages.no_data') }}
                </span>
                @include('partials.language-switch')
            </div>
        </div>
        <nav class="fs-nav d-none d-lg-flex align-items-center gap-3 mt-2 pt-2 border-top" aria-label="{{ __('messages.module_access') }}">
            <a href="{{ route('dashboard') }}" class="active" aria-current="page">{{ __('messages.dashboard') }}</a>
            <a href="#alertas">{{ __('messages.alerts') }}</a>
            <a href="{{ route('rotations.index') }}">{{ __('messages.rotations') }}</a>
            <a href="{{ route('reforestation.index') }}">{{ __('messages.reforestation') }}</a>
            <a href="{{ route('recommendations.index') }}">{{ __('messages.recommendations') }}</a>
            <a href="{{ route('imagery.index') }}">{{ __('messages.nasa_imagery') }}</a>
            <a href="{{ route('chat.index') }}">{{ __('messages.assistant') }}</a>
        </nav>
    </div>
</header>

<main class="container fs-main">

    {{-- Resultado actualización manual --}}
    @if (session('refresh_status'))
        <div class="alert alert-success my-2" role="alert">
            ✅ {{ session('refresh_status') }}
        </div>
    @endif
    @if (session('refresh_error'))
        <div class="alert alert-danger my-2" role="alert">
            ⚠️ {{ session('refresh_error') }}
        </div>
    @endif
    @if ($errors->any())
        <div class="alert alert-warning my-2" role="alert">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Filtros: parcela + rango de fechas --}}
    <form method="GET" action="{{ route('dashboard') }}" id="range-form" class="card fs-card my-3">
        <div class="card-body">
            <div class="row g-2">
                <div class="col-6 col-lg-3">
                    <input type="number" step="any" name="lat" class="form-control" placeholder="{{ __('messages.latitude_placeholder') }}" aria-label="{{ __('messages.latitude') }}" value="{{ $lat }}">
                </div>
                <div class="col-6 col-lg-3">
                    <input type="number" step="any" name="lon" class="form-control" placeholder="{{ __('messages.longitude_placeholder') }}" aria-label="{{ __('messages.longitude') }}" value="{{ $lon }}">
                </div>
                <div class="col-12 col-lg-2">
                    <button class="btn btn-success bg-fs-green border-0 w-100">{{ __('messages.view') }}</button>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap mt-2">
                <small class="text-uppercase text-muted">{{ __('messages.range') }}</small>
                <input type="date" name="desde" class="form-control form-control-sm" style="width:auto" value="{{ $desde }}" aria-label="{{ __('messages.from') }}">
                <span class="text-muted" aria-hidden="true">→</span>
                <input type="date" name="hasta" class="form-control form-control-sm" style="width:auto" value="{{ $hasta }}" aria-label="{{ __('messages.to') }}">
                <div class="btn-group btn-group-sm" role="group" aria-label="{{ __('messages.quick_ranges') }}">
                    <button type="button" class="btn btn-outline-secondary" data-days="7">{{ __('messages.days', ['count' => 7]) }}</button>
                    <button type="button" class="btn btn-outline-secondary" data-days="30">{{ __('messages.days', ['count' => 30]) }}</button>
                    <button type="button" class="btn btn-outline-secondary" data-days="90">{{ __('messages.days', ['count' => 90]) }}</button>
                    <button type="button" class="btn btn-outline-secondary" data-days="clear">{{ __('messages.all') }}</button>
                </div>
            </div>
            {{-- Accesos secundarios solo en móvil (el escritorio los tiene en el header) --}}
            <div class="d-lg-none d-flex flex-wrap gap-2 mt-2 pt-2 border-top">
                <a href="{{ route('recommendations.index') }}" class="btn btn-sm btn-outline-primary">{{ __('messages.recommendations') }}</a>
                <a href="{{ route('imagery.index') }}" class="btn btn-sm btn-outline-dark">{{ __('messages.nasa_imagery') }}</a>
                <a href="{{ route('advanced.index') }}" class="btn btn-sm btn-outline-secondary">{{ __('messages.advanced_nasa_data') }}</a>
            </div>
        </div>
    </form>

    {{-- Sincronización con NASA (plegable, fuera del formulario de filtros) --}}
    <div class="card fs-card mb-3">
        <div class="card-body py-2">
            <details>
                <summary class="fs-summary">
                    <span class="material-symbols-outlined fs-6 me-1">refresh</span>{{ __('messages.refresh_data') }}
                </summary>
                <form method="POST" action="{{ route('data.refresh') }}" class="mt-3">
                    @csrf
                    <input type="hidden" name="lat" value="{{ $lat }}">
                    <input type="hidden" name="lon" value="{{ $lon }}">
                    <div class="row g-2 align-items-end">
                        <div class="col-12 col-md-4">
                            <label class="form-label small text-muted mb-1" for="sync-days">{{ __('messages.sync_range') }}</label>
                            <select id="sync-days" name="days" class="form-select form-select-sm">
                                <option value="10" selected>{{ __('messages.last_days', ['count' => 10]) }}</option>
                                <option value="30">{{ __('messages.last_days', ['count' => 30]) }}</option>
                                <option value="90">{{ __('messages.last_days', ['count' => 90]) }}</option>
                                <option value="365">{{ __('messages.last_days', ['count' => 365]) }}</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label small text-muted mb-1" for="sync-desde">{{ __('messages.from_optional') }}</label>
                            <input id="sync-desde" type="date" name="desde" class="form-control form-control-sm">
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label small text-muted mb-1" for="sync-hasta">{{ __('messages.to_optional') }}</label>
                            <input id="sync-hasta" type="date" name="hasta" class="form-control form-control-sm">
                        </div>
                        <div class="col-12 col-md-2">
                            <button class="btn btn-warning w-100">{{ __('messages.refresh_data') }}</button>
                        </div>
                    </div>
                    <small class="text-muted d-block mt-2">{{ __('messages.sync_help') }}</small>
                </form>
            </details>
        </div>
    </div>

    @unless ($latest)
        @if ($hasRange)
            <div class="alert alert-info">
                {{ __('messages.no_records_range', ['from' => $desde ?? __('messages.start'), 'to' => $hasta ?? __('messages.today')]) }}
                {{ __('messages.adjust_range_or_refresh', ['refresh' => __('messages.refresh_data')]) }}
            </div>
        @else
            <div class="alert alert-info">{{ __('messages.no_records_database', ['table' => 'nasa_agro_data', 'refresh' => __('messages.refresh_data')]) }}</div>
        @endif
    @endunless

    @if ($latest)
        {{-- 1. Estado actual --}}
        <h2 class="fs-section-title">{{ __('messages.current_status') }}</h2>
        <div class="row g-2 mb-2">
            <div class="col-6 col-lg-3">
                <div class="card fs-card h-100">
                    <div class="card-body">
                        <small class="text-uppercase text-muted">{{ __('messages.ndvi_index') }} <span class="material-symbols-outlined fs-6 fs-green">trending_up</span></small>
                        <div class="fs-kpi" id="kpi-ndvi">{{ $latest->ndvi_value !== null ? number_format($latest->ndvi_value, 2) : '—' }}</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card fs-card h-100">
                    <div class="card-body">
                        <small class="text-uppercase text-muted">{{ __('messages.temperature') }} <span class="material-symbols-outlined fs-6">thermostat</span></small>
                        <div class="fs-kpi" id="kpi-temp">{{ $latest->temp !== null ? number_format($latest->temp, 1) : '—' }}<small class="fs-6 fw-normal">°C</small></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card fs-card h-100">
                    <div class="card-body">
                        <small class="text-uppercase text-muted">{{ __('messages.humidity') }} <span class="material-symbols-outlined fs-6 text-primary">water_drop</span></small>
                        <div class="fs-kpi" id="kpi-hum">{{ $latest->humidity !== null ? number_format($latest->humidity, 0) : '—' }}<small class="fs-6 fw-normal">%</small></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card fs-card h-100">
                    <div class="card-body">
                        <small class="text-uppercase text-muted">{{ __('messages.rainfall') }} <span class="material-symbols-outlined fs-6 text-primary">rainy</span></small>
                        <div class="fs-kpi" id="kpi-rain">{{ $latest->precip !== null ? number_format($latest->precip, 1) : '—' }}<small class="fs-6 fw-normal">mm</small></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Franja secundaria: acumulados y extremos --}}
        <div class="card fs-card bg-fs-sand mb-3">
            <div class="card-body py-2">
                <div class="row g-2">
                    <div class="col-6 col-lg-3">
                        <small class="text-uppercase text-muted d-block">{{ __('messages.wind') }} <span class="material-symbols-outlined fs-6">air</span></small>
                        <div class="fs-kpi-sm">{{ $latest->wind_speed !== null ? number_format((float) $latest->wind_speed * 3.6, 0) : '—' }}<small class="fs-6 fw-normal"> km/h</small></div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <small class="text-uppercase text-muted d-block">{{ __('messages.accumulated_rainfall', ['period' => $periodLabel]) }}</small>
                        <div class="fs-kpi-sm">{{ number_format($monthAccum, 1) }}<small class="fs-6 fw-normal"> mm</small></div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <small class="text-uppercase text-muted d-block">{{ __('messages.rainy_days_label') }}</small>
                        <div class="fs-kpi-sm">{{ $monthRainDays }}<small class="fs-6 fw-normal"> {{ __('messages.days_unit') }}</small></div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <small class="text-uppercase text-muted d-block">{{ __('messages.maximum') }} / {{ __('messages.minimum') }}</small>
                        <div class="fs-kpi-sm">
                            {{ $latest->temp_max !== null ? number_format($latest->temp_max, 1).'°' : '—' }}
                            <span class="text-muted">/</span>
                            {{ $latest->temp_min !== null ? number_format($latest->temp_min, 1).'°' : '—' }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- 2. Alertas (siempre visibles) --}}
    <div class="d-flex align-items-center justify-content-between mb-1" id="alertas">
        <h2 class="fs-section-title mb-0">{{ __('messages.latest_alerts') }}</h2>
        @if ($alerts->count() > 3)
            <button type="button" class="btn btn-sm btn-link text-success p-0" id="alerts-toggle"
                    data-label-more="{{ __('messages.view_all') }} ({{ $alerts->count() }})">
                {{ __('messages.view_all') }} ({{ $alerts->count() }})
            </button>
        @endif
    </div>
    @php
        $severity = [
            'lluvia_extrema' => 'bg-danger',
            'inundacion_encharcamiento' => 'bg-danger',
            'lluvia_fuerte' => 'bg-warning text-dark',
            'tormenta_probable' => 'bg-warning text-dark',
            'helada' => 'bg-info text-dark',
            'sequia' => 'bg-secondary',
            'estres_hidrico' => 'bg-secondary',
        ];
    @endphp
    <div class="card fs-card mb-3">
        <ul class="list-group list-group-flush">
            @forelse ($alerts as $alert)
                <li class="list-group-item {{ $loop->index >= 3 ? 'd-none fs-alert-extra' : '' }}">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="badge {{ $severity[$alert->risk_type] ?? 'bg-secondary' }}">{{ __('messages.risk_types.'.$alert->risk_type) }}</span>
                        <small class="text-muted">{{ $alert->created_at->format('Y-m-d H:i') }}{{ $alert->data_date ? ' · '.__('messages.data_date').' '.$alert->data_date->format('Y-m-d') : '' }}</small>
                    </div>
                    <div class="mt-1">{{ $alert->message }}</div>
                </li>
            @empty
                <li class="list-group-item text-muted">{{ __('messages.no_alerts') }}</li>
            @endforelse
        </ul>
    </div>

    @if ($latest)
        {{-- 3. Tendencias: un gráfico a la vez --}}
        <h2 class="fs-section-title">{{ __('messages.trends') }}</h2>
        <div class="card fs-card mb-3" data-fs-tabgroup>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-2">
                    <div class="btn-group btn-group-sm" role="tablist" aria-label="{{ __('messages.trends') }}">
                        <button type="button" class="btn btn-outline-secondary active" role="tab" aria-selected="true"
                                data-fs-tab="trend-vegetation">{{ __('messages.trend_vegetation') }}</button>
                        <button type="button" class="btn btn-outline-secondary" role="tab" aria-selected="false"
                                data-fs-tab="trend-rain">{{ __('messages.trend_rain') }}</button>
                    </div>
                </div>
                <div data-fs-panel="trend-vegetation" role="tabpanel">
                    <h3 class="h6 text-muted small">{{ __('messages.water_health_curve') }}</h3>
                    <canvas id="ndviChart" height="140"></canvas>
                </div>
                <div data-fs-panel="trend-rain" class="d-none" role="tabpanel">
                    <h3 class="h6 text-muted small">{{ __('messages.daily_rainfall', ['period' => $periodTitle]) }}</h3>
                    <canvas id="precipChart" height="140"></canvas>
                </div>
            </div>
        </div>

        {{-- 4. Detalle: eventos y registros en pestañas --}}
        <h2 class="fs-section-title">{{ __('messages.details') }}</h2>
        <div class="card fs-card mb-3" data-fs-tabgroup>
            <div class="card-body">
                <div class="btn-group btn-group-sm mb-2" role="tablist" aria-label="{{ __('messages.details') }}">
                    <button type="button" class="btn btn-outline-secondary active" role="tab" aria-selected="true"
                            data-fs-tab="detail-rain">{{ __('messages.rain_events_short') }}</button>
                    <button type="button" class="btn btn-outline-secondary" role="tab" aria-selected="false"
                            data-fs-tab="detail-recent">{{ __('messages.latest_nasa_records') }}</button>
                </div>
                <div data-fs-panel="detail-rain" role="tabpanel">
                    <p class="small text-muted mb-1">{{ __('messages.rain_events', ['period' => $periodLabel]) }}</p>
                    <ul class="list-group list-group-flush">
                        @forelse ($rainEvents as $e)
                            <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                <span>{{ $e->date->format('Y-m-d') }}</span>
                                <span class="badge {{ (float) $e->precip >= 50 ? 'bg-danger' : ((float) $e->precip >= 20 ? 'bg-warning text-dark' : 'bg-info text-dark') }}">
                                    {{ number_format($e->precip, 1) }} mm
                                </span>
                            </li>
                        @empty
                            <li class="list-group-item px-0 text-muted">{{ __('messages.no_rain_events') }}</li>
                        @endforelse
                    </ul>
                </div>
                <div data-fs-panel="detail-recent" class="d-none" role="tabpanel">
                    <ul class="list-group list-group-flush">
                        @foreach ($recent as $r)
                            <li class="list-group-item px-0 d-flex justify-content-between">
                                <span>{{ $r->date->format('Y-m-d') }}</span>
                                <span class="text-muted">{{ $r->temp !== null ? number_format($r->temp, 1).'°C' : '—' }} · {{ $r->precip !== null ? number_format($r->precip, 1).'mm' : '—' }}</span>
                            </li>
                        @endforeach
                    </ul>
                    <small class="text-muted">{{ __('messages.total_records', ['count' => $total]) }}</small>
                </div>
            </div>
        </div>
    @endif
</main>

{{-- Bottom nav solo móvil --}}
<nav class="fixed-bottom fs-bottom-nav shadow d-lg-none">
    <div class="d-flex justify-content-around align-items-center h-100">
        <a href="{{ route('dashboard') }}" class="text-center text-success text-decoration-none"><span class="material-symbols-outlined d-block">analytics</span>{{ __('messages.dashboard') }}</a>
        <a href="{{ route('chat.index') }}" class="text-center text-muted text-decoration-none"><span class="material-symbols-outlined d-block">chat</span>{{ __('messages.chat') }}</a>
        <a href="{{ route('rotations.index') }}" class="text-center text-muted text-decoration-none"><span class="material-symbols-outlined d-block">agriculture</span>{{ __('messages.rotations') }}</a>
        <a href="{{ route('reforestation.index') }}" class="text-center text-muted text-decoration-none"><span class="material-symbols-outlined d-block">forest</span>{{ __('messages.reforest') }}</a>
        <a href="#alertas" class="text-center text-muted text-decoration-none"><span class="material-symbols-outlined d-block">cloud</span>{{ __('messages.alerts') }}</a>
    </div>
</nav>

@if ($latest)
<script>
    (function () {
        const charts = {};
        function buildNdvi() {
            if (charts.ndvi) return;
            charts.ndvi = new Chart(document.getElementById('ndviChart'), {
                type: 'line',
                data: {
                    labels: @json($labels),
                    datasets: [
                        {label: 'NDVI', data: @json($ndvi), tension: 0.3, borderColor: '#0d631b'},
                        {label: @json(__('messages.temperature') . ' (°C)'), data: @json($temps), tension: 0.3, yAxisID: 'y1', borderColor: '#b26a00'}
                    ]
                },
                options: { scales: { y: {min: -0.2, max: 1}, y1: {position: 'right', grid: {drawOnChartArea: false}}} }
            });
        }
        function buildRain() {
            if (charts.rain) return;
            charts.rain = new Chart(document.getElementById('precipChart'), {
                type: 'bar',
                data: {
                    labels: @json($monthLabels),
                    datasets: [
                        {label: @json(__('messages.rainfall') . ' (mm)'), data: @json($monthPrecips), backgroundColor: '#2f7fe0'}
                    ]
                },
                options: { scales: { y: {beginAtZero: true}} }
            });
        }
        // Los gráficos de pestañas ocultas se crean al mostrarse (Chart.js mide 0 px si están ocultos).
        window.fsBuildChart = function (id) {
            if (id === 'trend-vegetation') buildNdvi();
            if (id === 'trend-rain') buildRain();
        };
        buildNdvi();
    })();
</script>
@endif
<script>
    (function () {
        // Pestañas simples (sin depender de Bootstrap JS)
        document.querySelectorAll('[data-fs-tabgroup]').forEach(function (group) {
            group.querySelectorAll('[data-fs-tab]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const id = btn.getAttribute('data-fs-tab');
                    group.querySelectorAll('[data-fs-tab]').forEach(function (b) {
                        const active = b === btn;
                        b.classList.toggle('active', active);
                        b.setAttribute('aria-selected', active ? 'true' : 'false');
                    });
                    group.querySelectorAll('[data-fs-panel]').forEach(function (p) {
                        p.classList.toggle('d-none', p.getAttribute('data-fs-panel') !== id);
                    });
                    if (typeof window.fsBuildChart === 'function') window.fsBuildChart(id);
                });
            });
        });

        // Alertas: mostrar todas / ver menos
        const alertsToggle = document.getElementById('alerts-toggle');
        if (alertsToggle) {
            alertsToggle.addEventListener('click', function () {
                const extras = document.querySelectorAll('.fs-alert-extra');
                const expand = extras.length > 0 && extras[0].classList.contains('d-none');
                extras.forEach(function (el) { el.classList.toggle('d-none', !expand); });
                alertsToggle.textContent = expand ? '{{ __('messages.show_less') }}' : alertsToggle.getAttribute('data-label-more');
            });
        }

        // Rangos rápidos de fecha
        const form = document.getElementById('range-form');
        if (!form) return;
        const pad = (n) => String(n).padStart(2, '0');
        const fmt = (d) => d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
        form.querySelectorAll('[data-days]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const value = btn.getAttribute('data-days');
                if (value === 'clear') {
                    form.desde.value = '';
                    form.hasta.value = '';
                } else {
                    const end = new Date();
                    const start = new Date(end);
                    start.setDate(end.getDate() - (parseInt(value, 10) - 1));
                    form.desde.value = fmt(start);
                    form.hasta.value = fmt(end);
                }
                form.requestSubmit();
            });
        });
    })();
</script>
@if (! $hasRange && ! $lat && ! $lon)
<script>
    // Refresco en vivo solo cuando el tablero muestra el último dato global.
    (function () {
        const fmt = (v, d = 2) => v === null || v === undefined ? '—' : Number(v).toFixed(d);
        async function refresh() {
            try {
                const r = await fetch('{{ route('dashboard.latest') }}');
                if (!r.ok) return;
                const j = await r.json();
                const temp = document.querySelector('#kpi-temp');
                if (temp && temp.firstChild) temp.firstChild.nodeValue = fmt(j.temp, 1);
                const hum = document.querySelector('#kpi-hum');
                if (hum && hum.firstChild) hum.firstChild.nodeValue = fmt(j.humidity, 0);
                const rain = document.querySelector('#kpi-rain');
                if (rain && rain.firstChild) rain.firstChild.nodeValue = fmt(j.precip, 1);
                const ndvi = document.querySelector('#kpi-ndvi');
                if (ndvi) ndvi.textContent = fmt(j.ndvi_value, 2);
            } catch (e) {}
        }
        setInterval(refresh, 30000);
    })();
</script>
@endif
</body>
</html>
