<?php

use App\Http\Controllers\Api\N8nAlertController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Webhook n8n -> Laravel (flujo: Cron -> MySQL -> Code -> Gemini -> HTTP POST aquí).
Route::post('/n8n/alerts', [N8nAlertController::class, 'store']);
