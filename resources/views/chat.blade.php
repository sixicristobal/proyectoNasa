<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('messages.chat_title') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4" style="max-width: 720px">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">{{ __('messages.agro_assistant') }}</h1>
        <div class="d-flex gap-2 align-items-center">
            @include('partials.language-switch')
            <a href="{{ route('dashboard') }}" class="btn btn-sm btn-outline-primary">{{ __('messages.back_to_dashboard') }}</a>
        </div>
    </div>

    @if ($errors->has('llm'))
        <div class="alert alert-warning">{{ $errors->first('llm') }}</div>
    @endif

    <div id="chat-error" class="alert alert-warning d-none"></div>

    <div class="card mb-3">
        <div class="card-body" id="chat-history">
            @forelse ($history as $msg)
                <div class="mb-2 p-2 rounded {{ $msg['role'] === 'user' ? 'bg-primary text-white' : 'bg-white border' }}">
                    <small class="d-block opacity-75">{{ $msg['role'] === 'user' ? __('messages.role_user') : __('messages.role_assistant') }}</small>
                    {{ $msg['content'] }}
                </div>
            @empty
                <p class="text-muted mb-0" id="chat-empty">{{ __('messages.chat_empty') }}</p>
            @endforelse
        </div>
    </div>

    <form method="POST" action="{{ route('chat.store') }}" id="chat-form" class="card card-body">
        @csrf
        <textarea name="message" class="form-control mb-2" rows="3" required placeholder="{{ __('messages.chat_placeholder') }}"></textarea>
        <div class="mb-2">
            <select id="city" class="form-select">
                <option value="">{{ __('messages.select_city') }}</option>
                <option value="-25.2637,-57.5759">Asunción</option>
                <option value="-25.3394,-57.5463">San Lorenzo</option>
                <option value="-25.3413,-57.5330">Luque</option>
                <option value="-25.5098,-54.6111">Ciudad del Este</option>
                <option value="-27.3333,-55.8667">Encarnación</option>
                <option value="-23.4425,-57.4431">Concepción</option>
            </select>
        </div>
        <div class="row g-2">
            <div class="col"><input type="number" step="any" name="lat" id="lat" class="form-control" placeholder="{{ __('messages.latitude') }}" aria-label="{{ __('messages.latitude') }}"></div>
            <div class="col"><input type="number" step="any" name="lon" id="lon" class="form-control" placeholder="{{ __('messages.longitude') }}" aria-label="{{ __('messages.longitude') }}"></div>
            <div class="col-auto"><button class="btn btn-success">{{ __('messages.send') }}</button></div>
        </div>
    </form>
</div>

@include('partials.ai-preloader', [
    'aiMessages' => [
        __('messages.ai_connecting'),
        __('messages.ai_reading_climate'),
        __('messages.ai_chat_context'),
        __('messages.ai_writing_reply'),
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
            userRole.textContent = @json(__('messages.role_user'));
            user.appendChild(userRole);
            user.appendChild(document.createTextNode(question));
            history.appendChild(user);

            var assistant = document.createElement('div');
            assistant.className = 'mb-2 p-2 rounded bg-white border';
            var assistantRole = document.createElement('small');
            assistantRole.className = 'd-block opacity-75';
            assistantRole.textContent = @json(__('messages.role_assistant'));
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
