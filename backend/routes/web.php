<?php

use Illuminate\Support\Facades\Route;

// The interface lives in the Next.js app; this application only serves the
// API (see app/Modules/*/routes.php) and its documentation at /docs/api.
Route::get('/', fn () => response()->json(['name' => config('app.name'), 'docs' => url('/docs/api')]));
