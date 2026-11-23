<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

Route::get('/', function () {
    return view('app');
});

Route::post('/api/login', [AuthController::class, 'login']);

Route::view('/{path}', 'app')->where('path', 'dashboard|job-positions|candidates|applications|hiring-pipeline|interview-schedule|reports-analytics|settings');
