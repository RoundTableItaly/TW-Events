<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('activities');
});

// This route allows a cron job to securely trigger the activity importer.
// The URL should be kept secret.
Route::get('/run-importer-d3a8e7f6c5b4a3b2c1d0e9f8a7b6c5d4', function () {
    Artisan::call('import:activities');
    return "Import command executed successfully.";
}); 