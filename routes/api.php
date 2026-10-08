<?php

use App\Http\Controllers\Api\N8nAlertController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Webhook n8n -> Laravel (flujo: Cron -> MySQL -> Code -> Gemini -> HTTP POST aquí).
Route::post('/n8n/alerts', [N8nAlertController::class, 'store']);

// Endpoint para que n8n pueda disparar la sincronización de NASA
Route::post('/n8n/sync-nasa', function () {
    \Illuminate\Support\Facades\Artisan::call('nasa:sync');
    return response()->json(['status' => 'success', 'message' => 'NASA data synced']);
});
