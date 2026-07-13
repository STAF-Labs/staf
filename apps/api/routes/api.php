<?php

use App\Http\Controllers\Api\Admin\Org\OrganizationController;
use App\Http\Controllers\Api\Admin\User\UserController;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request): ?Authenticatable {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/organizations', [OrganizationController::class, 'index']);
    Route::get('/organizations/{organization}', [OrganizationController::class, 'show']);
    Route::patch('/organizations/{organization}/block', [OrganizationController::class, 'block']);
    Route::patch('/organizations/{organization}/unblock', [OrganizationController::class, 'unblock']);
    Route::patch('/organizations/{organization}/freeze', [OrganizationController::class, 'freeze']);
    Route::patch('/organizations/{organization}/unfreeze', [OrganizationController::class, 'unfreeze']);
    Route::delete('/organizations/{organization}', [OrganizationController::class, 'destroy']);

    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{user}', [UserController::class, 'show']);
    Route::patch('/users/{user}/block', [UserController::class, 'block']);
    Route::patch('/users/{user}/unblock', [UserController::class, 'unblock']);
    Route::patch('/users/{user}/freeze', [UserController::class, 'freeze']);
    Route::patch('/users/{user}/unfreeze', [UserController::class, 'unfreeze']);
    Route::delete('/users/{user}', [UserController::class, 'destroy']);
});
