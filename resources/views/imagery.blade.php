<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Field Shift · Imágenes NASA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        body { background: #fff8f6; color: #2b160f; padding-bottom: 2rem; }
        #map { height: 60vh; min-height: 380px; border-radius: 1rem; }
        .fs-card { border: 0; border-radius: 1rem; box-shadow: 0 1px 6px rgba(0,0,0,.06); }
        .material-symbols-outlined { vertical-align: middle; }
    </style>
</head>
<body>
<main class="container pt-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <div class="text-uppercase text-muted" style="font-size:.65rem;letter-spacing:.08em;">Field Shift · NASA GIBS</div>
            <h1 class="h4 mb-0">🛰️ Imágenes satelitales con color</h1>
        </div>
        <a href="{{ route('dashboard') }}" class="btn btn-sm btn-outline-success">← Tablero</a>
    </div>

    {{-- Selector fecha / capa / parcela --}}
    <form method="GET" action="{{ route('imagery.index') }}" class="card fs-card mb-3">
        <div class="card-body row g-2">
            <div class="col-6 col-lg-2">
                <label class="form-label small text-muted">Fecha</label>
                <input type="date" name="fecha" class="form-control" value="{{ $date }}" max="{{ date('Y-m-d') }}">
            </div>
            <div class="col-6 col-lg-4">
                <label class="form-label small text-muted">Capa (colores)</label>
                <select name="capa" class="form-select">
                    @foreach ($layers as $key => $meta)
                        <option value="{{ $key }}" @selected($layer === $key)>{{ $meta['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-5 col-lg-2">
                <label class="form-label small text-muted">Lat</label>
                <input type="number" step="any" name="lat" class="form-control" value="{{ $lat }}">
            </div>
            <div class="col-5 col-lg-2">
                <label class="form-label small text-muted">Lon</label>
                <input type="number" step="any" name="lon" class="form-control" value="{{ $lon }}">
            </div>
            <div class="col-2 col-lg-2 d-flex align-items-end">
                <button class="btn btn-success w-100">Ver</button>
            </div>
            <div class="col-12">
                <small class="text-muted">{{ $layers[$layer]['desc'] }} · Fecha mostrada: <strong>{{ $date }}</strong></small>
            </div>
        </div>
    </form>

    <div class="card fs-card mb-3">
        <div class="card-body p-2">
            <div id="map"></div>
            <small class="text-muted d-block mt-2 px-2">{{ $attribution }}</small>
        </div>
    </div>

    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ $snapshot }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary"><span class="material-symbols-outlined fs-6">download</span> Descargar PNG (Worldview)</a>
        <a href="{{ $worldview }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-dark"><span class="material-symbols-outlined fs-6">open_in_new</span> Abrir en NASA Worldview</a>
    </div>
    <p class="text-muted small mt-2">Tip: si un día sale con huecos o nubes, retrocede 1–2 días (el mosaico diario tarda en completarse). La capa 7-2-1 muestra vegetación en verde e inundaciones en azul oscuro.</p>
</main>

<script>
    const map = L.map('map').setView([{{ $lat }}, {{ $lon }}], 6);
    const layers = {};
    @foreach ($templates as $key => $tpl)
        layers["{{ $key }}"] = L.tileLayer("{{ $tpl }}", {maxZoom: 9, minZoom: 2, attribution: @json($attribution)});
    @endforeach
    layers[@json($layer)].addTo(map);
    L.marker([{{ $lat }}, {{ $lon }}]).addTo(map).bindPopup("Parcela ({{ $lat }}, {{ $lon }})").openPopup();
    L.control.layers(
        {@foreach ($layers as $key => $meta) "{{ $meta['label'] }}": layers["{{ $key }}"]@if (!$loop->last),@endif @endforeach}
    ).addTo(map);
</script>
</body>
</html>
