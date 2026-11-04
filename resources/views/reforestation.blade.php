<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Field Shift · Reforestación</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4" style="max-width: 720px">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Planificador de reforestación (IA)</h1>
        <a href="{{ route('dashboard') }}" class="btn btn-sm btn-outline-primary">Dashboard</a>
    </div>

    @if ($errors->has('llm'))
        <div class="alert alert-warning">{{ $errors->first('llm') }}</div>
    @endif

    <div id="reforestation-error" class="alert alert-warning d-none"></div>

    <form method="POST" action="{{ route('reforestation.store') }}" id="reforestation-form" class="card card-body mb-3">
        @csrf
        <label class="form-label">Tipo de suelo</label>
        <input type="text" name="suelo" class="form-control mb-2" required placeholder="Ej: rojo, arenoso, arcilloso">
        <label class="form-label">Objetivo (opcional)</label>
        <input type="text" name="objetivo" class="form-control mb-2" placeholder="Ej: cortina rompeviento, madera">
        <div class="row g-2">
            <div class="col"><input type="number" step="any" name="lat" class="form-control" placeholder="Lat"></div>
            <div class="col"><input type="number" step="any" name="lon" class="form-control" placeholder="Lon"></div>
            <div class="col-auto"><button class="btn btn-success">Planificar</button></div>
        </div>
    </form>

    <div id="reforestation-result" class="card mb-3 {{ $result ? '' : 'd-none' }}">
        <div class="card-header bg-transparent">Plan generado por la IA local</div>
        <div class="card-body" style="white-space: pre-line">{{ $result }}</div>
    </div>

    <div class="card">
        <div class="card-header">Especies nativas disponibles</div>
        <ul class="list-group list-group-flush">
            @foreach ($species as $s)
                <li class="list-group-item"><strong>{{ $s['nombre'] }}</strong> · Suelos: {{ implode(', ', $s['suelos']) }} · {{ $s['clima'] }}</li>
            @endforeach
        </ul>
    </div>
</div>

@include('partials.ai-preloader', [
    'aiMessages' => [
        'Conectando con la IA…',
        'Leyendo el tipo de suelo que declaraste…',
        'Cruzando el clima NASA de tu latitud y longitud…',
        'La IA selecciona especies nativas del catálogo…',
        'Redactando época de plantación y cuidados…',
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
