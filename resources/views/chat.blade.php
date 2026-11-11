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

    @if ($errors->has('llm'))
        <div class="alert alert-warning">{{ $errors->first('llm') }}</div>
    @endif

    <div id="chat-error" class="alert alert-warning d-none"></div>

    <div class="card mb-3">
        <div class="card-body" id="chat-history">
            @forelse ($history as $msg)
                <div class="mb-2 p-2 rounded {{ $msg['role'] === 'user' ? 'bg-primary text-white' : 'bg-white border' }}">
                    <small class="d-block opacity-75">{{ $msg['role'] }}</small>
                    {{ $msg['content'] }}
                </div>
            @empty
                <p class="text-muted mb-0" id="chat-empty">Pregunta sobre siembra, riego, sequía o heladas.</p>
            @endforelse
        </div>
    </div>

    <form method="POST" action="{{ route('chat.store') }}" id="chat-form" class="card card-body">
        @csrf
        <textarea name="message" class="form-control mb-2" rows="3" required placeholder="¿Riesgo de helada esta semana?"></textarea>
        <div class="mb-2">
            <select id="city" class="form-select">
                <option value="">— Ciudad (Paraguay) —</option>
                <option value="-25.2637,-57.5759">Asunción</option>
                <option value="-25.3394,-57.5463">San Lorenzo</option>
                <option value="-25.3413,-57.5330">Luque</option>
                <option value="-25.5098,-54.6111">Ciudad del Este</option>
                <option value="-27.3333,-55.8667">Encarnación</option>
                <option value="-23.4425,-57.4431">Concepción</option>
            </select>
        </div>
        <div class="row g-2">
            <div class="col"><input type="number" step="any" name="lat" id="lat" class="form-control" placeholder="Lat"></div>
            <div class="col"><input type="number" step="any" name="lon" id="lon" class="form-control" placeholder="Lon"></div>
            <div class="col-auto"><button class="btn btn-success">Enviar</button></div>
        </div>
    </form>
</div>

@include('partials.ai-preloader', [
    'aiMessages' => [
        'Conectando con la IA…',
        'Leyendo datos climáticos y NDVI de la NASA…',
        'La IA recuerda el contexto de la conversación…',
        'Redactando la respuesta…',
    ],
])

<script>
    document.getElementById('city')?.addEventListener('change', function () {
        if (!this.value) return;
        const [lat, lon] = this.value.split(',');
        document.getElementById('lat').value = lat;
        document.getElementById('lon').value = lon;
    });
    aiAsk({
        form: document.getElementById('chat-form'),
        url: @json(route('chat.store')),
        onSuccess: function (data) {
            var history = document.getElementById('chat-history');
            var empty = document.getElementById('chat-empty');
            if (empty) {
                empty.remove();
            }
            var question = document.getElementById('chat-form').querySelector('[name="message"]').value;

            var user = document.createElement('div');
            user.className = 'mb-2 p-2 rounded bg-primary text-white';
            var userRole = document.createElement('small');
            userRole.className = 'd-block opacity-75';
            userRole.textContent = 'user';
            user.appendChild(userRole);
            user.appendChild(document.createTextNode(question));
            history.appendChild(user);

            var assistant = document.createElement('div');
            assistant.className = 'mb-2 p-2 rounded bg-white border';
            var assistantRole = document.createElement('small');
            assistantRole.className = 'd-block opacity-75';
            assistantRole.textContent = 'assistant';
            assistant.appendChild(assistantRole);
            assistant.appendChild(document.createTextNode(data.reply));
            history.appendChild(assistant);

            document.getElementById('chat-form').reset();
            document.getElementById('chat-error').classList.add('d-none');
            history.scrollTop = history.scrollHeight;
        },
        onError: function (message) {
            var error = document.getElementById('chat-error');
            error.textContent = message;
            error.classList.remove('d-none');
        }
    });
</script>
</body>
</html>
