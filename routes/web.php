<?php

use App\Http\Controllers\AdvancedDataController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DataRefreshController;
use App\Http\Controllers\ImageryController;
use App\Http\Controllers\RecommendationController;
use App\Http\Controllers\ReforestationController;
use App\Http\Controllers\RotationComparisonController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

Route::get('/api/latest', [DashboardController::class, 'latest'])->name('dashboard.latest');

Route::post('/actualizar', [DataRefreshController::class, 'store'])->name('data.refresh');

Route::get('/imagenes', [ImageryController::class, 'index'])->name('imagery.index');
Route::get('/datos-avanzados', [AdvancedDataController::class, 'index'])->name('advanced.index');

Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');
Route::post('/chat', [ChatController::class, 'store'])->name('chat.store');

Route::get('/recomendaciones', [RecommendationController::class, 'index'])->name('recommendations.index');
Route::post('/recomendaciones', [RecommendationController::class, 'store'])->name('recommendations.store');

Route::get('/reforestacion', [ReforestationController::class, 'index'])->name('reforestation.index');
Route::post('/reforestacion', [ReforestationController::class, 'store'])->name('reforestation.store');

Route::get('/rotaciones', [RotationComparisonController::class, 'index'])->name('rotations.index');
Route::post('/rotaciones', [RotationComparisonController::class, 'store'])->name('rotations.store');
Route::post('/rotaciones/parcela', [RotationComparisonController::class, 'storeField'])->name('rotations.fields.store');
