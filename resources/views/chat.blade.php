<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Field Shift · Asistente</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4" style="max-width: 720px">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Asistente agroecológico · Paraguay</h1>
        <a href="{{ route('dashboard') }}" class="btn btn-sm btn-outline-primary">Dashboard</a>
    </div>

    @if ($errors->has('ollama'))
        <div class="alert alert-warning">{{ $errors->first('ollama') }}</div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            @forelse ($history as $msg)
                <div class="mb-2 p-2 rounded {{ $msg['role'] === 'user' ? 'bg-primary text-white' : 'bg-white border' }}">
                    <small class="d-block opacity-75">{{ $msg['role'] }}</small>
                    {{ $msg['content'] }}
                </div>
            @empty
                <p class="text-muted mb-0">Pregunta sobre siembra, riego, sequía o heladas.</p>
            @endforelse
        </div>
    </div>

    <form method="POST" action="{{ route('chat.store') }}" class="card card-body">
        @csrf
        <textarea name="message" class="form-control mb-2" rows="3" required placeholder="¿Riesgo de helada esta semana?"></textarea>
        <div class="row g-2">
            <div class="col"><input type="number" step="any" name="lat" class="form-control" placeholder="Lat"></div>
            <div class="col"><input type="number" step="any" name="lon" class="form-control" placeholder="Lon"></div>
            <div class="col-auto"><button class="btn btn-success">Enviar</button></div>
        </div>
    </form>
</div>
</body>
</html>
