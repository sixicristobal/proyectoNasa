<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Field Shift · Dashboard NDVI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
</head>
<body class="bg-light">
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Field Shift · Salud hídrica (NDVI)</h1>
        <div class="d-flex gap-2">
            <a href="{{ route('recommendations.index') }}" class="btn btn-sm btn-outline-primary">Recomendaciones</a>
            <a href="{{ route('reforestation.index') }}" class="btn btn-sm btn-outline-primary">Reforestación</a>
            <a href="{{ route('chat.index') }}" class="btn btn-sm btn-outline-success">Asistente agroecológico</a>
        </div>
    </div>

    <form method="GET" action="{{ route('dashboard') }}" class="row g-2 mb-4">
        <div class="col-md-3">
            <input type="number" step="any" name="lat" class="form-control" placeholder="Lat (-25.2637)" value="{{ $lat }}">
        </div>
        <div class="col-md-3">
            <input type="number" step="any" name="lon" class="form-control" placeholder="Lon (-57.5759)" value="{{ $lon }}">
        </div>
        <div class="col-md-2">
            <button class="btn btn-primary w-100">Filtrar</button>
        </div>
    </form>

    <div class="card mb-4">
        <div class="card-body">
            <canvas id="ndviChart" height="120"></canvas>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Últimas alertas (n8n + Gemini)</div>
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
</div>
<script>
    new Chart(document.getElementById('ndviChart'), {
        type: 'line',
        data: {
            labels: @json($labels),
            datasets: [
                {label: 'NDVI', data: @json($ndvi), tension: 0.3},
                {label: 'Temp (°C)', data: @json($temps), tension: 0.3, yAxisID: 'y1'}
            ]
        },
        options: {
            scales: {
                y: {min: -0.2, max: 1},
                y1: {position: 'right', grid: {drawOnChartArea: false}}
            }
        }
    });
</script>
</body>
</html>
