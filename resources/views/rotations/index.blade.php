<!doctype html>
<html lang="{{ app()->getLocale() }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('messages.rotation_title') }}</title>
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
                <div class="text-uppercase text-muted small">{{ __('messages.field_shift_dashboard') }}</div>
                <h1 class="h4 mb-0">{{ __('messages.rotation_comparator') }}</h1>
                <p class="text-muted mb-0 small">
                    {{ __('messages.rotation_strategies_desc') }}
                </p>
            </div>
            <div class="d-flex gap-2 align-items-center">
                @include('partials.language-switch')
                <a href="{{ route('dashboard') }}" class="btn btn-sm btn-outline-success">
                    <span class="material-symbols-outlined fs-6">arrow_back</span> {{ __('messages.dashboard') }}
                </a>
            </div>
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
                {{ __('messages.crop_catalog_empty') }}
                <code>php artisan db:seed --class=CropSeeder</code>
            </div>
        @endunless

        @if ($fields->isEmpty())
            <div class="card fs-card mb-3">
                <div class="card-body">
                    <h2 class="h6">{{ __('messages.create_field_first') }}</h2>
                    <p class="text-muted small mb-3">
                        {{ __('messages.field_recommendations_explanation') }}
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
                                <label for="field_id" class="form-label small text-muted mb-1">{{ __('messages.parcel') }}</label>
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
                                        {{ __('messages.generate_recommendations') }}
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>

                    <details class="mt-3">
                        <summary class="small text-success">{{ __('messages.add_another_field') }}</summary>
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
                            <span class="badge text-bg-light">{{ __('messages.irrigation_types.'.$field->irrigation_type) }}</span>
                            <span class="badge text-bg-light">{{ $field->soil_type ?? __('messages.soil_no_data') }}</span>
                            @if ($field->area_ha)
                                <span class="badge text-bg-light">{{ number_format($field->area_ha, 2) }} ha</span>
                            @endif
                        </div>
                        <div class="text-muted small mt-1">
                            {{ __('messages.coordinates') }}: {{ number_format($field->latitude, 5) }}, {{ number_format($field->longitude, 5) }}
                            · {{ __('messages.ph') }}: {{ $field->soil_ph ?? __('messages.no_data_short') }}
                            · {{ __('messages.organic_matter') }}: {{ $field->soil_organic_matter_pct !== null ? $field->soil_organic_matter_pct.' %' : __('messages.no_data_short') }}
                        </div>
                    </div>
                </div>

                @if ($recommendations->isEmpty())
                    <div class="alert alert-info">
                        {{ __('messages.no_recommendations_yet') }}
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
                                            <strong class="d-block">{{ $recommendation->rotation?->name ?? __('messages.proposed_rotation') }}</strong>
                                        </div>
                                        <span class="badge {{ $confidenceClass }} fs-chip">
                                            {{ __('messages.confidence', ['value' => number_format($confidence * 100, 0)]) }}
                                        </span>
                                    </div>
                                    <div class="card-body">
                                        <div class="fs-confidence mb-3" title="{{ __('messages.estimated_confidence') }}">
                                            <span style="width: {{ max(5, min(100, $confidence * 100)) }}%"></span>
                                        </div>

                                        @if ($recommendation->rotation?->steps->isNotEmpty())
                                            <div class="d-flex flex-wrap gap-1 mb-3">
                                                @foreach ($recommendation->rotation->steps as $step)
                                                    <span class="badge rounded-pill text-bg-light fs-chip">
                                                        {{ $step->sequence }}. {{ $step->crop?->name ?? __('messages.crop') }}
                                                        · {{ $step->season }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        @endif

                                        <h3 class="h6 text-success">{{ __('messages.suggested_because') }}</h3>
                                        <p class="small">{{ $recommendation->why_suggested }}</p>

                                        <h3 class="h6 text-success">{{ __('messages.evidence') }}</h3>
                                        <p class="small">{{ $recommendation->evidence }}</p>

                                        <h3 class="h6 text-success">{{ __('messages.benefits') }}</h3>
                                        <p class="small">{{ $recommendation->benefits }}</p>

                                        <h3 class="h6 text-danger">{{ __('messages.risks') }}</h3>
                                        <p class="small">{{ $recommendation->risks }}</p>

                                        <h3 class="h6 text-secondary">{{ __('messages.information_gaps') }}</h3>
                                        <p class="small mb-0">{{ $recommendation->information_gaps }}</p>
                                    </div>
                                    <div class="card-footer bg-transparent">
                                        @if ($recommendation->requires_local_validation)
                                            <span class="badge text-bg-warning fs-chip">{{ __('messages.requires_local_validation') }}</span>
                                        @else
                                            <span class="badge text-bg-success fs-chip">{{ __('messages.sufficient_for_initial_decision') }}</span>
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
                <h2 class="h6">{{ __('messages.quick_glossary') }}</h2>
                <dl class="row small mb-0">
                    <dt class="col-sm-3">NDVI</dt>
                    <dd class="col-sm-9">{{ __('messages.ndvi_glossary') }}</dd>
                    <dt class="col-sm-3">{{ __('messages.evapotranspiration') }}</dt>
                    <dd class="col-sm-9">{{ __('messages.evapotranspiration_definition') }}</dd>
                    <dt class="col-sm-3">{{ __('messages.smap_moisture') }}</dt>
                    <dd class="col-sm-9">{{ __('messages.smap_definition') }}</dd>
                    <dt class="col-sm-3">{{ __('messages.dry_spell') }}</dt>
                    <dd class="col-sm-9">{{ __('messages.dry_spell_definition') }}</dd>
                    <dt class="col-sm-3">{{ __('messages.satellite_vs_ground') }}</dt>
                    <dd class="col-sm-9 mb-0">
                        {{ __('messages.satellite_vs_ground_definition') }}
                    </dd>
                </dl>
            </div>
        </div>
    </main>
</body>

</html>
