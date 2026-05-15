<?php

use App\Http\Controllers\Api\MonitorController;
use Illuminate\Support\Facades\Route;

Route::prefix('monitors')->group(function () {
    Route::post('/', [MonitorController::class, 'store']);
    Route::get('/', [MonitorController::class, 'index']);
    Route::get('/{id}/history', [MonitorController::class, 'history']);
    Route::get('/{id}/stats', [MonitorController::class, 'stats']);
});
