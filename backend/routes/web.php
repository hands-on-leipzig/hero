<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// The built SPA is copied into public/ on deploy; client-side routes all serve its index.html.
Route::fallback(function (Request $request) {
    $index = public_path('index.html');
    abort_if($request->is('api/*') || ! is_file($index), 404);

    return response()->file($index, ['Cache-Control' => 'no-cache']);
});
