<?php

use App\Http\Controllers\API\ExecuteController;
use App\Http\Controllers\API\PowerController;
use App\Http\Controllers\API\SensorController;
use Illuminate\Support\Facades\Route;

Route::get('/power/index', [PowerController::class, 'index']);
Route::post('/power/get', [PowerController::class, 'get']);

Route::get('/sensor/index', [SensorController::class, 'index']);
Route::post('/sensor/get', [SensorController::class, 'get']);

Route::post('/execute', [ExecuteController::class, 'index']);
Route::post('/sensor/execute-get', [SensorController::class, 'execute_get']);
