{{--
    Preloader reutilizable: overlay con el logo mientras se ejecuta una consulta.
    Se activa en el submit de cualquier formulario de la página.
--}}
@once
<style>
    .fs-preloader {
        position: fixed;
        inset: 0;
        z-index: 2000;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1rem;
        background: rgba(15, 23, 42, .55);
        backdrop-filter: blur(4px);
        opacity: 0;
        visibility: hidden;
        transition: opacity .18s ease, visibility .18s ease;
    }
    .fs-preloader.is-active { opacity: 1; visibility: visible; }
    .fs-preloader__card {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: .9rem;
        padding: 1.75rem 2.25rem;
        border-radius: 1rem;
        background: #fff8f6;
        box-shadow: 0 18px 45px rgba(0, 0, 0, .28);
    }
    .fs-preloader__logo {
        width: 6rem;
        height: 6rem;
        object-fit: contain;
        animation: fs-preloader-pulse 1.4s ease-in-out infinite;
    }
    .fs-preloader__ring {
        width: 2.25rem;
        height: 2.25rem;
        border-radius: 50%;
        border: 3px solid rgba(13, 99, 27, .16);
        border-top-color: #0d631b;
        animation: fs-preloader-spin .8s linear infinite;
    }
    .fs-preloader__text {
        margin: 0;
        font-weight: 600;
        color: #0d631b;
        font-size: .95rem;
    }
    @keyframes fs-preloader-spin { to { transform: rotate(360deg); } }
    @keyframes fs-preloader-pulse {
        0%, 100% { transform: scale(1); opacity: .9; }
        50% { transform: scale(1.06); opacity: 1; }
    }
    @media (prefers-reduced-motion: reduce) {
        .fs-preloader__logo, .fs-preloader__ring { animation: none; }
    }
</style>
@endonce

<div id="fs-preloader" class="fs-preloader" role="status" aria-live="polite" aria-hidden="true">
    <div class="fs-preloader__card">
        <img src="{{ asset('img/logo.png') }}" alt="" class="fs-preloader__logo">
        <div class="fs-preloader__ring" aria-hidden="true"></div>
        <p class="fs-preloader__text">{{ __('messages.loading_data') }}</p>
    </div>
</div>

@once
<script>
    (function () {
        const preloader = document.getElementById('fs-preloader');
        if (!preloader) return;

        const show = () => {
            preloader.classList.add('is-active');
            preloader.setAttribute('aria-hidden', 'false');
        };
        const hide = () => {
            preloader.classList.remove('is-active');
            preloader.setAttribute('aria-hidden', 'true');
        };

        document.querySelectorAll('form').forEach((form) => {
            // Los formularios con IA (chat, recomendaciones, reforestación) usan
            // fetch + su propio preloader; no deben disparar este overlay.
            if (form.hasAttribute('data-ai-form')) return;

            form.addEventListener('submit', (event) => {
                if (event.defaultPrevented) return;
                if (typeof form.checkValidity === 'function' && !form.checkValidity()) return;

                show();
                form.querySelectorAll('button[type="submit"], button:not([type])').forEach((btn) => {
                    btn.disabled = true;
                });
            });
        });

        // Al volver con el botón del navegador (bfcache) ocultamos el overlay.
        window.addEventListener('pageshow', hide);
        window.addEventListener('pagehide', hide);
    })();
</script>
@endonce
