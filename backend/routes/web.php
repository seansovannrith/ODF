<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json([
    'app' => config('app.name'),
    'message' => 'Organization Document Flow API. Use /api/* endpoints.',
]));
