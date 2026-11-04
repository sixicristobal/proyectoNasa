<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Field Shift · Recomendaciones</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
    <div class="container py-4" style="max-width: 720px">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h4 mb-0">Recomendaciones (IA)</h1>
            <a href="{{ route('dashboard') }}" class="btn btn-sm btn-outline-primary">Dashboard</a>
        </div>

        @if ($errors->has('llm'))
            <div class="alert alert-warning">{{ $errors->first('llm') }}</div>
        @endif

        <div id="recommendation-error" class="alert alert-warning d-none"></div>

        <form method="POST" action="{{ route('recommendations.store') }}" id="recommendation-form" class="card card-body mb-3">
            @csrf
            <div class="row g-2">
                <div class="col-md-4">
                    <select name="topic" class="form-select" required>
                        <option value="siembra">Siembra</option>
                        <option value="riego">Riego</option>
                        <option value="rotacion">Rotación</option>
                    </select>
                </div>
                <div class="col"><input type="number" step="any" name="lat" class="form-control"
                        placeholder="Lat"></div>
                <div class="col"><input type="number" step="any" name="lon" class="form-control"
                        placeholder="Lon"></div>
                <div class="col-auto"><button class="btn btn-success">Consultar</button></div>
            </div>
        </form>

        @if ($result)
            <div class="card mb-3">
                <div class="card-body" style="white-space: pre-line">{{ $result }}</div>
            </div>
        @endif

        <div id="recommendation-result" class="card mb-3 d-none">
            <div class="card-header bg-transparent">Recomendación de la IA local</div>
            <div class="card-body" style="white-space: pre-line"></div>
        </div>
    </div>

    @include('partials.ai-preloader', [
        'aiMessages' => [
            'Conectando con la IA…',
            'Leyendo datos climáticos y NDVI de la NASA…',
            'La IA analiza el tema elegido…',
            'Redactando la recomendación agronómica…',
        ],
    ])

    <script>
        aiAsk({
            form: document.getElementById('recommendation-form'),
            url: @json(route('recommendations.store')),
            onSuccess: function (data) {
                var card = document.getElementById('recommendation-result');
                card.querySelector('.card-body').textContent = data.result;
                card.classList.remove('d-none');
                document.getElementById('recommendation-error').classList.add('d-none');
                card.scrollIntoView({ behavior: 'smooth', block: 'start' });
            },
            onError: function (message) {
                var error = document.getElementById('recommendation-error');
                error.textContent = message;
                error.classList.remove('d-none');
            }
        });
    </script>
</body>

</html>
