<?php

use App\Http\Controllers\Settings\ApiTokenController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('api/tokens', [ApiTokenController::class, 'index'])->name('api-tokens.index');
    Route::post('api/tokens', [ApiTokenController::class, 'store'])->name('api-tokens.store');
    Route::delete('api/tokens/{tokenId}', [ApiTokenController::class, 'destroy'])->name('api-tokens.destroy');
});
