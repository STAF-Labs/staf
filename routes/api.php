<?php

use App\Http\Controllers\Api\User\UserIndexController;
use App\Http\Controllers\Api\User\UserProfileIndexController;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request): ?Authenticatable {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/users', UserIndexController::class);
    Route::get('/user-profiles', UserProfileIndexController::class);
});
