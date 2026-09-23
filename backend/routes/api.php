<?php

use App\Http\Controllers\Admin\SharepointSettingsController;
use App\Http\Controllers\SharepointController;
use App\Http\Controllers\VolunteerController;
use Illuminate\Support\Facades\Route;

Route::get('/ping', fn () => response()->json(['ok' => true]));

Route::get('/volunteer-openings', [VolunteerController::class, 'openings']);
Route::post('/volunteer-inquiries', [VolunteerController::class, 'inquire'])->middleware('throttle:8,1');

Route::middleware('keycloak')->prefix('sharepoint')->group(function () {
    Route::get('/status', [SharepointController::class, 'status']);
    Route::get('/documents', [SharepointController::class, 'documents']);
    Route::get('/documents-file-stream', [SharepointController::class, 'stream']);
});

Route::middleware('keycloak:admin')->prefix('admin')->group(function () {
    Route::get('/sharepoint', [SharepointSettingsController::class, 'show']);
    Route::put('/sharepoint', [SharepointSettingsController::class, 'update']);
    Route::post('/sharepoint/test', [SharepointSettingsController::class, 'test']);
});
