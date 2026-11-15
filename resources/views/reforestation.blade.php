<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Field Shift · {{ __('messages.reforestation') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
@include('partials.preloader')
<div class="container py-4" style="max-width: 720px">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">{{ __('messages.reforestation_planner') }}</h1>
        <div class="d-flex gap-2 align-items-center">
            @include('partials.language-switch')
            <a href="{{ route('dashboard') }}" class="btn btn-sm btn-outline-primary">{{ __('messages.back_to_dashboard') }}</a>
        </div>
    </div>

    @if ($errors->has('llm'))
        <div class="alert alert-warning">{{ $errors->first('llm') }}</div>
    @endif

    <div id="reforestation-error" class="alert alert-warning d-none"></div>

    <form method="POST" action="{{ route('reforestation.store') }}" id="reforestation-form" class="card card-body mb-3" data-ai-form>
        @csrf
        <label class="form-label">{{ __('messages.soil_type') }}</label>
        <input type="text" name="suelo" class="form-control mb-2" required placeholder="{{ __('messages.soil_type_example') }}">
        <label class="form-label">{{ __('messages.objective_optional') }}</label>
        <input type="text" name="objetivo" class="form-control mb-2" placeholder="{{ __('messages.objective_example') }}">
        <div class="row g-2">
            <div class="col"><input type="number" step="any" name="lat" class="form-control" placeholder="{{ __('messages.latitude') }}" aria-label="{{ __('messages.latitude') }}"></div>
            <div class="col"><input type="number" step="any" name="lon" class="form-control" placeholder="{{ __('messages.longitude') }}" aria-label="{{ __('messages.longitude') }}"></div>
            <div class="col-auto"><button class="btn btn-success">{{ __('messages.plan') }}</button></div>
        </div>
    </form>

    <div id="reforestation-result" class="card mb-3 {{ $result ? '' : 'd-none' }}">
        <div class="card-header bg-transparent">{{ __('messages.local_ai_plan') }}</div>
        <div class="card-body" style="white-space: pre-line">{{ $result }}</div>
    </div>

    <div class="card">
        <div class="card-header">{{ __('messages.native_species') }}</div>
        <ul class="list-group list-group-flush">
            @foreach ($species as $s)
                <li class="list-group-item"><strong>{{ $s['nombre'] }}</strong> · Suelos: {{ implode(', ', $s['suelos']) }} · {{ $s['clima'] }}</li>
            @endforeach
        </ul>
    </div>
</div>

@include('partials.ai-preloader', [
    'aiMessages' => [
        __('messages.ai_connecting'),
        __('messages.ai_reading_soil'),
        __('messages.ai_matching_climate'),
        __('messages.ai_selecting_species'),
        __('messages.ai_writing_care'),
    ],
])

<script>
    aiAsk({
        form: document.getElementById('reforestation-form'),
        url: @json(route('reforestation.store')),
        onSuccess: function (data) {
            var card = document.getElementById('reforestation-result');
            card.querySelector('.card-body').textContent = data.result;
            card.classList.remove('d-none');
            var error = document.getElementById('reforestation-error');
            error.classList.add('d-none');
            card.scrollIntoView({ behavior: 'smooth', block: 'start' });
        },
        onError: function (message) {
            var error = document.getElementById('reforestation-error');
            error.textContent = message;
            error.classList.remove('d-none');
        }
    });
</script>
</body>
</html>
