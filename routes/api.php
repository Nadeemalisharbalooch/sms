<?php

use App\Http\Controllers\Institute\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Route for the current user to update their own information
    Route::put('user/current', [UserController::class, 'updateCurrent']);
});

// Reverb private channel auth for token clients (mobile/API). The route
// registered by withRouting(channels:) only accepts a web session, so without
// this the mobile app cannot subscribe to App.Models.User.{id} at all.
Broadcast::routes(['middleware' => ['auth:sanctum']]);

require __DIR__ . '/auth.php';
require __DIR__ . '/institute/api.php';
