{{-- Preloader IA: overlay mientras el proveedor configurado (Gemini u Ollama) genera la respuesta. --}}
@php
    $aiMessages = $aiMessages ?? [
        'Conectando con la IA…',
        'Leyendo datos climáticos NASA de tu zona…',
        'La IA está analizando el contexto…',
        'Generando la recomendación…',
    ];
    $aiTimeoutMs = $aiTimeoutMs ?? 720000; // margen amplio para Gemini u Ollama en CPU
@endphp

<style>
    #ai-preloader {
        position: fixed;
        inset: 0;
        z-index: 2050;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 1rem;
        background: rgba(15, 23, 42, .74);
        backdrop-filter: blur(2px);
    }
    #ai-preloader.is-active { display: flex; }
    .ai-preloader-card {
        width: min(92vw, 420px);
        background: #0f172a;
        color: #e2e8f0;
        border: 1px solid rgba(148, 163, 184, .35);
        border-radius: 1rem;
        padding: 1.75rem;
        text-align: center;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, .55);
    }
    .ai-orb { position: relative; width: 84px; height: 84px; margin: 0 auto 1rem; }
    .ai-orb span {
        position: absolute;
        inset: 0;
        border-radius: 50%;
        border: 2px solid transparent;
        animation: ai-spin 1.6s linear infinite;
    }
    .ai-orb span:nth-child(1) { border-top-color: #38bdf8; }
    .ai-orb span:nth-child(2) {
        inset: 10px;
        border-right-color: #34d399;
        animation-duration: 2.2s;
        animation-direction: reverse;
    }
    .ai-orb span:nth-child(3) { inset: 20px; border-bottom-color: #a78bfa; animation-duration: 1.2s; }
    .ai-orb-core {
        position: absolute;
        inset: 30px;
        border-radius: 50%;
        background: radial-gradient(circle at 30% 30%, #7dd3fc, #0ea5e9 60%, #0369a1);
        animation: ai-pulse 1.8s ease-in-out infinite;
    }
    .ai-preloader-status { min-height: 2.75rem; font-size: .95rem; }
    .ai-preloader-elapsed { font-variant-numeric: tabular-nums; color: #94a3b8; font-size: .85rem; }
    .ai-preloader-hint { color: #64748b; font-size: .78rem; margin-top: .75rem; }
    @keyframes ai-spin { to { transform: rotate(360deg); } }
    @keyframes ai-pulse {
        0%, 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(56, 189, 248, .55); }
        50% { transform: scale(1.15); box-shadow: 0 0 0 12px rgba(56, 189, 248, 0); }
    }
</style>

<div id="ai-preloader" role="status" aria-live="polite">
    <div class="ai-preloader-card">
        <div class="ai-orb"><span></span><span></span><span></span><div class="ai-orb-core"></div></div>
        <h2 class="h6 mb-1">La IA está analizando…</h2>
        <p class="ai-preloader-status mb-0" id="ai-preloader-status">Conectando con la IA…</p>
        <p class="ai-preloader-elapsed mb-1" id="ai-preloader-elapsed">0 s</p>
        <p class="ai-preloader-hint mb-0">El proveedor de IA puede tardar unos segundos o varios minutos según la carga.</p>
    </div>
</div>

<script>
(function () {
    var messages = @json($aiMessages);
    var timeoutMs = {{ (int) $aiTimeoutMs }};

    window.aiAsk = function (options) {
        var form = options.form;
        var overlay = document.getElementById('ai-preloader');
        var statusEl = document.getElementById('ai-preloader-status');
        var elapsedEl = document.getElementById('ai-preloader-elapsed');
        var statusTimer = null;
        var elapsedTimer = null;

        function start() {
            var index = 0;
            statusEl.textContent = messages[0];
            statusTimer = setInterval(function () {
                index = (index + 1) % messages.length;
                statusEl.textContent = messages[index];
            }, 3000);
            var started = Date.now();
            elapsedTimer = setInterval(function () {
                var s = Math.floor((Date.now() - started) / 1000);
                elapsedEl.textContent = s < 60 ? s + ' s' : Math.floor(s / 60) + ' min ' + (s % 60) + ' s';
            }, 1000);
            overlay.classList.add('is-active');
        }

        function stop() {
            clearInterval(statusTimer);
            clearInterval(elapsedTimer);
            overlay.classList.remove('is-active');
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            start();

            var controller = new AbortController();
            var guard = setTimeout(function () { controller.abort(); }, timeoutMs);

            fetch(options.url, {
                method: 'POST',
                body: new FormData(form),
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value
                },
                signal: controller.signal
            })
                .then(function (response) {
                    return response.json().catch(function () { return {}; }).then(function (payload) {
                        return { ok: response.ok, status: response.status, payload: payload };
                    });
                })
                .then(function (data) {
                    clearTimeout(guard);
                    stop();
                    if (!data.ok || data.payload.error) {
                        var message = data.payload.error;
                        if (!message && data.payload.errors) {
                            message = Object.keys(data.payload.errors).map(function (key) {
                                return data.payload.errors[key][0];
                            }).join(' ');
                        }
                        options.onError(message || 'La IA no pudo responder (HTTP ' + data.status + ').');
                        return;
                    }
                    options.onSuccess(data.payload);
                })
                .catch(function (error) {
                    clearTimeout(guard);
                    stop();
                    options.onError(error.name === 'AbortError'
                        ? 'La IA tardó más de ' + Math.round(timeoutMs / 60000) + ' minutos. Reintenta o revisa la configuración del proveedor.'
                        : 'No se pudo contactar con la IA: ' + error.message);
                });
        });
    };
})();
</script>
