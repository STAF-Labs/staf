<?php

use App\Http\Controllers\Api\Admin\User\UserController;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request): ?Authenticatable {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{user}', [UserController::class, 'show']);
    Route::patch('/users/{user}/block', [UserController::class, 'block']);
    Route::patch('/users/{user}/unblock', [UserController::class, 'unblock']);
    Route::delete('/users/{user}', [UserController::class, 'destroy']);
});
