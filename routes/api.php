<?php

use Illuminate\Support\Facades\Route;

Route::get('/ping', function () {
    return response()->json(['status' => 'ok', 'framework' => 'Laravel 13', 'php' => PHP_VERSION]);
});
