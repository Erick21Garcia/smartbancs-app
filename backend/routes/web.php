<?php

use App\Http\Controllers\MetricsController;

Route::get('/metrics', [MetricsController::class, 'index']);