<?php

use App\Http\Controllers\ChatController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\RecommendationController;
use App\Http\Controllers\ReforestationController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');
Route::post('/chat', [ChatController::class, 'store'])->name('chat.store');

Route::get('/recomendaciones', [RecommendationController::class, 'index'])->name('recommendations.index');
Route::post('/recomendaciones', [RecommendationController::class, 'store'])->name('recommendations.store');

Route::get('/reforestacion', [ReforestationController::class, 'index'])->name('reforestation.index');
Route::post('/reforestacion', [ReforestationController::class, 'store'])->name('reforestation.store');
