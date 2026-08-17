<?php

use App\Http\Controllers\Institute\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Route for the current user to update their own information
    Route::put('user/current', [UserController::class, 'updateCurrent']);
});

require __DIR__ . '/auth.php';
require __DIR__ . '/institute/api.php';
