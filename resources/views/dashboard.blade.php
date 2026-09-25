<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Field Shift · Tablero</title>
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
        .fs-header { background: rgba(255,248,246,.92); backdrop-filter: blur(12px); }
        .fs-live-dot { width: .5rem; height: .5rem; }
        .fs-kpi { font-size: 2rem; font-weight: 700; line-height: 1; }
        .fs-card { border: 0; border-radius: 1rem; box-shadow: 0 1px 6px rgba(0,0,0,.06); }
        .fs-green { color: var(--fs-green); }
        .bg-fs-green { background: var(--fs-green); }
        .bg-fs-soft { background: var(--fs-green-soft); }
        .bg-fs-sand { background: var(--fs-sand); }
        .fs-bottom-nav { height: 4rem; background: rgba(255,248,246,.92); backdrop-filter: blur(12px); }
        .fs-bottom-nav a { font-size: .65rem; text-transform: uppercase; letter-spacing: .04em; }
        .material-symbols-outlined { vertical-align: middle; }
    </style>
</head>
<body>

{{-- Header fijo estilo mockup móvil --}}
<header class="fixed-top fs-header shadow-sm">
    <div class="container py-2">
        <div class="d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <span class="rounded-circle bg-fs-green text-white d-inline-flex align-items-center justify-content-center" style="width:2rem;height:2rem;">
                    <span class="material-symbols-outlined fs-6">eco</span>
                </span>
                <div>
                    <div class="text-uppercase text-muted" style="font-size:.65rem;letter-spacing:.08em;">Field Shift · Tablero DSS</div>
                    <strong>Parcela monitoreada</strong>
                </div>
            </div>
            <span class="badge bg-fs-soft text-success d-none d-sm-inline-block">
                <span class="spinner-grow spinner-grow-sm fs-live-dot" role="status"></span>
                NASA POWER · {{ $latest?->date->format('Y-m-d') ?? 'sin datos' }}
            </span>
        </div>
    </div>
</header>

<main class="container pt-5 mt-4">

    {{-- Filtro parcela --}}
    <form method="GET" action="{{ route('dashboard') }}" class="row g-2 my-3">
        <div class="col-5 col-lg-3">
            <input type="number" step="any" name="lat" class="form-control" placeholder="Lat (-25.2637)" value="{{ $lat }}">
        </div>
        <div class="col-5 col-lg-3">
            <input type="number" step="any" name="lon" class="form-control" placeholder="Lon (-57.5759)" value="{{ $lon }}">
        </div>
        <div class="col-2 col-lg-2">
            <button class="btn btn-success bg-fs-green border-0 w-100">Ver</button>
        </div>
        <div class="col-12 col-lg-4 d-flex gap-2 justify-content-lg-end">
            <a href="{{ route('chat.index') }}" class="btn btn-sm btn-outline-success">Asistente</a>
            <a href="{{ route('recommendations.index') }}" class="btn btn-sm btn-outline-primary d-none d-lg-inline-block">Recomendaciones</a>
            <a href="{{ route('reforestation.index') }}" class="btn btn-sm btn-outline-primary d-none d-lg-inline-block">Reforestación</a>
        </div>
    </form>

    {{-- Accesos a módulos --}}
    <div class="row g-2 mb-3">
        <div class="col-6 col-lg-3">
            <a href="{{ route('chat.index') }}" class="btn btn-success bg-fs-green border-0 w-100 py-3"><span class="material-symbols-outlined d-block">chat</span>Asistente</a>
        </div>
        <div class="col-6 col-lg-3">
            <a href="{{ route('recommendations.index') }}" class="btn btn-primary w-100 py-3"><span class="material-symbols-outlined d-block">psychology</span>Recomendaciones</a>
        </div>
        <div class="col-6 col-lg-3">
            <a href="{{ route('reforestation.index') }}" class="btn btn-outline-success w-100 py-3"><span class="material-symbols-outlined d-block">forest</span>Reforestación</a>
        </div>
        <div class="col-6 col-lg-3">
            <a href="#alertas" class="btn btn-outline-secondary w-100 py-3"><span class="material-symbols-outlined d-block">cloud</span>Alertas</a>
        </div>
    </div>

    @if ($latest)
        {{-- Hero telemetría: apilado en móvil, 4 columnas en PC --}}
        <div class="row g-2 mb-3">
            <div class="col-12 col-lg-3">
                <div class="card fs-card h-100">
                    <div class="card-body">
                        <small class="text-uppercase text-muted">Índice NDVI <span class="material-symbols-outlined fs-6 fs-green">trending_up</span></small>
                        <div class="fs-kpi">{{ $latest->ndvi_value !== null ? number_format($latest->ndvi_value, 2) : '—' }}</div>
                        <span class="badge bg-fs-soft text-success">NDVI {{ $latest->date->format('Y-m-d') }}</span>
                    </div>
                </div>
            </div>
            <div class="col-4 col-lg-3">
                <div class="card fs-card h-100">
                    <div class="card-body">
                        <small class="text-uppercase text-muted">Temp <span class="material-symbols-outlined fs-6">thermostat</span></small>
                        <div class="fs-kpi">{{ $latest->temp !== null ? number_format($latest->temp, 1) : '—' }}<small class="fs-6 fw-normal">°C</small></div>
                    </div>
                </div>
            </div>
            <div class="col-4 col-lg-3">
                <div class="card fs-card h-100">
                    <div class="card-body">
                        <small class="text-uppercase text-muted">Humedad <span class="material-symbols-outlined fs-6 text-primary">water_drop</span></small>
                        <div class="fs-kpi">{{ $latest->humidity !== null ? number_format($latest->humidity, 0) : '—' }}<small class="fs-6 fw-normal">%</small></div>
                    </div>
                </div>
            </div>
            <div class="col-4 col-lg-3">
                <div class="card fs-card h-100">
                    <div class="card-body">
                        <small class="text-uppercase text-muted">Lluvia <span class="material-symbols-outlined fs-6 text-primary">rainy</span></small>
                        <div class="fs-kpi">{{ $latest->precip !== null ? number_format($latest->precip, 1) : '—' }}<small class="fs-6 fw-normal">mm</small></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-2 mb-3">
            {{-- Curva NDVI --}}
            <div class="col-12 col-lg-8">
                <div class="card fs-card h-100">
                    <div class="card-body">
                        <h2 class="h6">Curva de salud hídrica (NDVI)</h2>
                        <canvas id="ndviChart" height="140"></canvas>
                    </div>
                </div>
            </div>
            {{-- Últimos registros NASA POWER --}}
            <div class="col-12 col-lg-4">
                <div class="card fs-card h-100">
                    <div class="card-body">
                        <h2 class="h6">Últimos registros NASA POWER</h2>
                        <ul class="list-group list-group-flush">
                            @foreach ($recent as $r)
                                <li class="list-group-item px-0 d-flex justify-content-between">
                                    <span>{{ $r->date->format('Y-m-d') }}</span>
                                    <span class="text-muted">{{ $r->temp !== null ? number_format($r->temp, 1).'°C' : '—' }} · {{ $r->precip !== null ? number_format($r->precip, 1).'mm' : '—' }}</span>
                                </li>
                            @endforeach
                        </ul>
                        <small class="text-muted">{{ $total }} registros en total.</small>
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="alert alert-info">Sin registros en <code>nasa_agro_data</code>. Ejecuta <code>php artisan nasa:sync</code> para traer datos NASA POWER.</div>
    @endif

    {{-- Alertas reales --}}
    <div class="card fs-card mb-3" id="alertas">
        <div class="card-header bg-transparent">Últimas alertas (n8n + Gemini)</div>
        <ul class="list-group list-group-flush">
            @forelse ($alerts as $alert)
                <li class="list-group-item">
                    <strong>{{ $alert->risk_type }}</strong> · {{ $alert->created_at->format('Y-m-d H:i') }}
                    <br>{{ $alert->message }}
                </li>
            @empty
                <li class="list-group-item text-muted">Sin alertas todavía.</li>
            @endforelse
        </ul>
    </div>
</main>

{{-- Bottom nav solo móvil, estilo dasResponsive --}}
<nav class="fixed-bottom fs-bottom-nav shadow d-lg-none">
    <div class="d-flex justify-content-around align-items-center h-100">
        <a href="{{ route('dashboard') }}" class="text-center text-success text-decoration-none"><span class="material-symbols-outlined d-block">analytics</span>Tablero</a>
        <a href="{{ route('chat.index') }}" class="text-center text-muted text-decoration-none"><span class="material-symbols-outlined d-block">chat</span>Chat</a>
        <a href="{{ route('recommendations.index') }}" class="text-center text-muted text-decoration-none"><span class="material-symbols-outlined d-block">psychology</span>IA</a>
        <a href="{{ route('reforestation.index') }}" class="text-center text-muted text-decoration-none"><span class="material-symbols-outlined d-block">forest</span>Reforestar</a>
        <a href="#alertas" class="text-center text-muted text-decoration-none"><span class="material-symbols-outlined d-block">cloud</span>Alertas</a>
    </div>
</nav>

@if ($latest)
<script>
    new Chart(document.getElementById('ndviChart'), {
        type: 'line',
        data: {
            labels: @json($labels),
            datasets: [
                {label: 'NDVI', data: @json($ndvi), tension: 0.3, borderColor: '#0d631b'},
                {label: 'Temp (°C)', data: @json($temps), tension: 0.3, yAxisID: 'y1', borderColor: '#b26a00'}
            ]
        },
        options: { scales: { y: {min: -0.2, max: 1}, y1: {position: 'right', grid: {drawOnChartArea: false}}} }
    });
</script>
@endif
</body>
</html>
