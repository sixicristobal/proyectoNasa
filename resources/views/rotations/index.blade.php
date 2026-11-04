<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Field Shift · Comparador de rotaciones</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    <style>
        :root { --fs-green: #0d631b; --fs-soft: #bdefbe; --fs-cream: #fff8f6; }
        body { background: var(--fs-cream); }
        .fs-card { border: 0; border-radius: 1rem; box-shadow: 0 1px 6px rgba(0,0,0,.06); }
        .fs-chip { font-size: .75rem; }
        .fs-confidence { height: .5rem; border-radius: 999px; background: #e9ecef; overflow: hidden; }
        .fs-confidence > span { display: block; height: 100%; background: var(--fs-green); }
        .material-symbols-outlined { vertical-align: middle; }
    </style>
</head>

<body>
    <main class="container py-4">
        <div class="d-flex align-items-start justify-content-between gap-2 mb-3">
            <div>
                <div class="text-uppercase text-muted small">Field Shift · DSS agrícola</div>
                <h1 class="h4 mb-0">Comparador de rotaciones</h1>
                <p class="text-muted mb-0 small">
                    Estrategias generadas con reglas agronómicas, evidencia NASA y tus prioridades.
                </p>
            </div>
            <a href="{{ route('dashboard') }}" class="btn btn-sm btn-outline-success">
                <span class="material-symbols-outlined fs-6">arrow_back</span> Tablero
            </a>
        </div>

        @if (session('status'))
            <div class="alert alert-success" role="alert">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-warning" role="alert">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @unless ($hasCrops)
            <div class="alert alert-warning" role="alert">
                El catálogo de cultivos está vacío. Ejecutá en el contenedor:
                <code>php artisan db:seed --class=CropSeeder</code>
            </div>
        @endunless

        @if ($fields->isEmpty())
            <div class="card fs-card mb-3">
                <div class="card-body">
                    <h2 class="h6">Primero creá una parcela</h2>
                    <p class="text-muted small mb-3">
                        Las recomendaciones se calculan por parcela usando sus coordenadas y observaciones NASA.
                    </p>
                    @include('rotations.partials.field-form')
                </div>
            </div>
        @else
            <div class="card fs-card mb-3">
                <div class="card-body">
                    <div class="row g-2 align-items-end">
                        <div class="col-12 col-lg-6">
                            <form method="GET" action="{{ route('rotations.index') }}">
                                <label for="field_id" class="form-label small text-muted mb-1">Parcela</label>
                                <select name="field_id" id="field_id" class="form-select" onchange="this.form.submit()">
                                    @foreach ($fields as $option)
                                        <option value="{{ $option->id }}" @selected($field && $field->id === $option->id)>
                                            {{ $option->name }} · {{ $option->farm?->name }} ({{ number_format($option->latitude, 4) }}, {{ number_format($option->longitude, 4) }})
                                        </option>
                                    @endforeach
                                </select>
                            </form>
                        </div>
                        <div class="col-12 col-lg-6 d-flex gap-2 justify-content-lg-end">
                            @if ($field)
                                <form method="POST" action="{{ route('rotations.store') }}">
                                    @csrf
                                    <input type="hidden" name="field_id" value="{{ $field->id }}">
                                    <button class="btn btn-success">
                                        <span class="material-symbols-outlined fs-6">auto_awesome</span>
                                        Generar / actualizar recomendaciones
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>

                    <details class="mt-3">
                        <summary class="small text-success">Agregar otra parcela</summary>
                        <div class="mt-3">
                            @include('rotations.partials.field-form')
                        </div>
                    </details>
                </div>
            </div>

            @if ($field)
                <div class="card fs-card mb-3">
                    <div class="card-body">
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <h2 class="h6 mb-0">{{ $field->name }}</h2>
                            <span class="badge text-bg-light">{{ $field->irrigation_type }}</span>
                            <span class="badge text-bg-light">{{ $field->soil_type ?? 'suelo sin dato' }}</span>
                            @if ($field->area_ha)
                                <span class="badge text-bg-light">{{ number_format($field->area_ha, 2) }} ha</span>
                            @endif
                        </div>
                        <div class="text-muted small mt-1">
                            Coordenadas: {{ number_format($field->latitude, 5) }}, {{ number_format($field->longitude, 5) }}
                            · pH: {{ $field->soil_ph ?? 'sin dato' }}
                            · Materia orgánica: {{ $field->soil_organic_matter_pct !== null ? $field->soil_organic_matter_pct.' %' : 'sin dato' }}
                        </div>
                    </div>
                </div>

                @if ($recommendations->isEmpty())
                    <div class="alert alert-info">
                        Todavía no hay recomendaciones para esta parcela. Usá “Generar / actualizar recomendaciones”.
                    </div>
                @else
                    <div class="row row-cols-1 row-cols-lg-2 row-cols-xl-3 g-3">
                        @foreach ($recommendations as $recommendation)
                            @php
                                $confidence = (float) ($recommendation->confidence ?? 0);
                                $confidenceClass = $confidence >= 0.7 ? 'text-bg-success' : ($confidence >= 0.4 ? 'text-bg-warning' : 'text-bg-danger');
                            @endphp
                            <div class="col">
                                <article class="card fs-card h-100">
                                    <div class="card-header bg-transparent d-flex justify-content-between align-items-start gap-2">
                                        <div>
                                            <span class="badge text-bg-dark fs-chip">#{{ $recommendation->rank }}</span>
                                            <strong class="d-block">{{ $recommendation->rotation?->name ?? 'Rotación propuesta' }}</strong>
                                        </div>
                                        <span class="badge {{ $confidenceClass }} fs-chip">
                                            Confianza {{ number_format($confidence * 100, 0) }}%
                                        </span>
                                    </div>
                                    <div class="card-body">
                                        <div class="fs-confidence mb-3" title="Confianza estimada del recomendador">
                                            <span style="width: {{ max(5, min(100, $confidence * 100)) }}%"></span>
                                        </div>

                                        @if ($recommendation->rotation?->steps->isNotEmpty())
                                            <div class="d-flex flex-wrap gap-1 mb-3">
                                                @foreach ($recommendation->rotation->steps as $step)
                                                    <span class="badge rounded-pill text-bg-light fs-chip">
                                                        {{ $step->sequence }}. {{ $step->crop?->name ?? 'cultivo' }}
                                                        · {{ $step->season }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        @endif

                                        <h3 class="h6 text-success">Por qué se sugiere</h3>
                                        <p class="small">{{ $recommendation->why_suggested }}</p>

                                        <h3 class="h6 text-success">Evidencia</h3>
                                        <p class="small">{{ $recommendation->evidence }}</p>

                                        <h3 class="h6 text-success">Beneficios</h3>
                                        <p class="small">{{ $recommendation->benefits }}</p>

                                        <h3 class="h6 text-danger">Riesgos</h3>
                                        <p class="small">{{ $recommendation->risks }}</p>

                                        <h3 class="h6 text-secondary">Lagunas de información</h3>
                                        <p class="small mb-0">{{ $recommendation->information_gaps }}</p>
                                    </div>
                                    <div class="card-footer bg-transparent">
                                        @if ($recommendation->requires_local_validation)
                                            <span class="badge text-bg-warning fs-chip">Requiere validación local</span>
                                        @else
                                            <span class="badge text-bg-success fs-chip">Datos suficientes para una primera decisión</span>
                                        @endif
                                    </div>
                                </article>
                            </div>
                        @endforeach
                    </div>
                @endif
            @endif
        @endif

        <div class="card fs-card mt-4">
            <div class="card-body">
                <h2 class="h6">Glosario rápido</h2>
                <dl class="row small mb-0">
                    <dt class="col-sm-3">NDVI</dt>
                    <dd class="col-sm-9">Índice de vegetación por satélite; más verde no siempre significa más agua disponible.</dd>
                    <dt class="col-sm-3">Evapotranspiración</dt>
                    <dd class="col-sm-9">Agua que se evapora del suelo y transpiran las plantas.</dd>
                    <dt class="col-sm-3">Humedad SMAP</dt>
                    <dd class="col-sm-9">Humedad superficial del suelo medida por satélite, en m³/m³.</dd>
                    <dt class="col-sm-3">Racha seca</dt>
                    <dd class="col-sm-9">Días consecutivos con menos de 1 mm de lluvia según GPM IMERG.</dd>
                    <dt class="col-sm-3">Observación satelital vs. medición terrestre</dt>
                    <dd class="col-sm-9 mb-0">
                        El satélite estima un promedio de un píxel; la medición terrestre (calicata, sensor de suelo)
                        es puntual. Por eso toda recomendación pide validación local.
                    </dd>
                </dl>
            </div>
        </div>
    </main>
</body>

</html>
