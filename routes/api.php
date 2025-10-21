<?php

use App\Http\Controllers\Api\V1\ImageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group.
|
*/

// V1 API Routes
Route::prefix('v1')->group(function () {
    // Protected routes (require authentication)
    Route::middleware('auth:sanctum')->group(function () {
        // Image endpoints
        Route::apiResource('images', ImageController::class);
    });
});
