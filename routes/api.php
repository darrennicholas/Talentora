<?php

use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CandidateController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InterviewController;
use App\Http\Controllers\JobPositionController;
use App\Http\Controllers\PipelineController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingsController;
use App\Http\Middleware\EnsureAdmin;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', App\Http\Middleware\EnsureActive::class])->group(function () {
    Route::get('/dashboard', DashboardController::class);

    Route::get('/job-positions', [JobPositionController::class, 'index']);
    Route::post('/job-positions', [JobPositionController::class, 'store']);
    Route::get('/job-positions/{job}', [JobPositionController::class, 'show'])->whereNumber('job');
    Route::patch('/job-positions/{job}', [JobPositionController::class, 'update'])->whereNumber('job');
    Route::patch('/job-positions/{job}/status', [JobPositionController::class, 'updateStatus'])->whereNumber('job');
    Route::delete('/job-positions/{job}', [JobPositionController::class, 'destroy'])->whereNumber('job');

    Route::get('/candidates', [CandidateController::class, 'index']);
    Route::post('/candidates', [CandidateController::class, 'store']);
    Route::get('/candidates/{candidate}', [CandidateController::class, 'show'])->whereNumber('candidate');
    Route::patch('/candidates/{candidate}', [CandidateController::class, 'update'])->whereNumber('candidate');
    Route::delete('/candidates/{candidate}', [CandidateController::class, 'destroy'])->whereNumber('candidate');
    Route::post('/candidates/{candidate}/cv', [CandidateController::class, 'uploadCv'])->whereNumber('candidate');
    Route::get('/candidates/{candidate}/cv', [CandidateController::class, 'downloadCv'])->whereNumber('candidate');
    Route::delete('/candidates/{candidate}/cv', [CandidateController::class, 'deleteCv'])->whereNumber('candidate');

    Route::get('/applications', [ApplicationController::class, 'index']);
    Route::post('/applications', [ApplicationController::class, 'store']);
    Route::get('/applications/{app}', [ApplicationController::class, 'show'])->whereNumber('app');
    Route::patch('/applications/{app}', [ApplicationController::class, 'update'])->whereNumber('app');
    Route::patch('/applications/{app}/stage', [ApplicationController::class, 'updateStage'])->whereNumber('app');
    Route::get('/applications/{app}/history', [ApplicationController::class, 'history'])->whereNumber('app');
    Route::get('/pipeline', [PipelineController::class, 'index']);

    Route::get('/interviews/calendar', [InterviewController::class, 'calendar']);
    Route::get('/interviewers', [InterviewController::class, 'interviewers']);
    Route::get('/interviews', [InterviewController::class, 'index']);
    Route::post('/interviews', [InterviewController::class, 'store']);
    Route::get('/interviews/{interview}', [InterviewController::class, 'show'])->whereNumber('interview');
    Route::patch('/interviews/{interview}', [InterviewController::class, 'update'])->whereNumber('interview');
    Route::patch('/interviews/{interview}/complete', [InterviewController::class, 'complete'])->whereNumber('interview');
    Route::patch('/interviews/{interview}/cancel', [InterviewController::class, 'cancel'])->whereNumber('interview');

    Route::get('/reports/{type}', [ReportController::class, 'show'])->where('type', 'summary|stages|trends|positions|metrics');

    Route::middleware(EnsureAdmin::class)->group(function () {
        Route::get('/users', [SettingsController::class, 'users']);
        Route::post('/users', [SettingsController::class, 'createUser']);
        Route::patch('/users/{user}', [SettingsController::class, 'updateUser'])->whereNumber('user');
        Route::patch('/users/{user}/status', [SettingsController::class, 'updateUserStatus'])->whereNumber('user');
        Route::get('/app-settings', [SettingsController::class, 'appSettings']);
        Route::patch('/app-settings', [SettingsController::class, 'updateAppSettings']);
    });
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [SettingsController::class, 'profile']);
    Route::patch('/me', [SettingsController::class, 'updateProfile']);
    Route::patch('/me/password', [SettingsController::class, 'updatePassword']);
});
