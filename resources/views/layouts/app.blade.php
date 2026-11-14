<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Field Shift')</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('android-chrome-192x192.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    @stack('head')
</head>
<body>
    <div style="position:fixed;top:10px;right:10px;z-index:9999;">
        <a href="{{ route('lang.switch','es') }}" class="btn btn-sm btn-outline-secondary">ES</a>
        <a href="{{ route('lang.switch','en') }}" class="btn btn-sm btn-outline-secondary">EN</a>
    </div>
    <header class="p-2">
        <img src="{{ asset('img/logo.png') }}" alt="Field Shift" style="height:2.5rem;">
    </header>
    <main class="container">
        @yield('content')
    </main>
    @stack('scripts')
</body>
</html>