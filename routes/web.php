<?php

use App\Http\Controllers\Institute\TimetableController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Website URL for opening or downloading a class timetable PDF.
Route::middleware('auth:sanctum')
    ->get('institutes/timetable/export/classes', [TimetableController::class, 'export'])
    ->name('institutes.timetable.export.classes');
