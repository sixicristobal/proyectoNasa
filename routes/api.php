<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// NOTA: flujo n8n eliminado. La ingesta la hace el Scheduler de Laravel
// (routes/console.php -> nasa:sync diario) y las alertas las genera
// el comando local `php artisan agro:check-alerts` con Ollama.
// Se dejan estas rutas solo como compatibilidad deshabilitada:
// responder 410 Gone si algún workflow viejo de n8n sigue llamando.
Route::post('/n8n/alerts', fn () => response()->json([
    'ok' => false,
    'error' => 'Flujo n8n eliminado. Usa el Scheduler Laravel + Ollama (ver INSTRUCCION.md).',
], 410));

Route::post('/n8n/sync-nasa', fn () => response()->json([
    'ok' => false,
    'error' => 'Flujo n8n eliminado. Usa `php artisan nasa:sync` o el Scheduler.',
], 410));
