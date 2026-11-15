<!doctype html>
<html lang="{{ app()->getLocale() }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Field Shift · {{ __('messages.recommendations') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
@include('partials.preloader')
    <div class="container py-4" style="max-width: 720px">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h4 mb-0">{{ __('messages.recommendations_ai') }}</h1>
            <div class="d-flex gap-2 align-items-center">
                @include('partials.language-switch')
                <a href="{{ route('dashboard') }}" class="btn btn-sm btn-outline-primary">{{ __('messages.back_to_dashboard') }}</a>
            </div>
        </div>

        @if ($errors->has('llm'))
            <div class="alert alert-warning">{{ $errors->first('llm') }}</div>
        @endif

        <div id="recommendation-error" class="alert alert-warning d-none"></div>

        <form method="POST" action="{{ route('recommendations.store') }}" id="recommendation-form" class="card card-body mb-3" data-ai-form>
            @csrf
            <div class="row g-2">
                <div class="col-md-4">
                    <select name="topic" class="form-select" required>
                        <option value="siembra">{{ __('messages.topic_planting') }}</option>
                        <option value="riego">{{ __('messages.topic_irrigation') }}</option>
                        <option value="rotacion">{{ __('messages.topic_rotation') }}</option>
                    </select>
                </div>
                <div class="col"><input type="number" step="any" name="lat" class="form-control"
                        placeholder="{{ __('messages.latitude') }}" aria-label="{{ __('messages.latitude') }}"></div>
                <div class="col"><input type="number" step="any" name="lon" class="form-control"
                        placeholder="{{ __('messages.longitude') }}" aria-label="{{ __('messages.longitude') }}"></div>
                <div class="col-auto"><button class="btn btn-success">{{ __('messages.ask') }}</button></div>
            </div>
        </form>

        @if ($result)
            <div class="card mb-3">
                <div class="card-body" style="white-space: pre-line">{{ $result }}</div>
            </div>
        @endif

        <div id="recommendation-result" class="card mb-3 d-none">
            <div class="card-header bg-transparent">{{ __('messages.local_ai_recommendation') }}</div>
            <div class="card-body" style="white-space: pre-line"></div>
        </div>
    </div>

    @include('partials.ai-preloader', [
        'aiMessages' => [
            __('messages.ai_connecting'),
            __('messages.ai_reading_climate'),
            __('messages.ai_analyzing'),
            __('messages.ai_writing_reply'),
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
