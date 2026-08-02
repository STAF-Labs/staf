<?php

use App\Http\Controllers\Api\Admin\ContentType\ContentTypeController;
use App\Http\Controllers\Api\Admin\Game\GameContentTypeController;
use App\Http\Controllers\Api\Admin\Game\GameController;
use App\Http\Controllers\Api\Admin\Game\GameDimensionController;
use App\Http\Controllers\Api\Admin\Game\GameDimensionValueController;
use App\Http\Controllers\Api\Admin\Org\OrganizationController;
use App\Http\Controllers\Api\Admin\Project\ProjectController;
use App\Http\Controllers\Api\Admin\User\UserController;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request): ?Authenticatable {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/games', [GameController::class, 'index']);
    Route::post('/games', [GameController::class, 'store']);
    Route::get('/games/{game}', [GameController::class, 'show']);
    Route::patch('/games/{game}', [GameController::class, 'update']);
    Route::delete('/games/{game}', [GameController::class, 'destroy']);
    Route::get('/games/{game}/content-types', [GameContentTypeController::class, 'index']);
    Route::post('/games/{game}/content-types', [GameContentTypeController::class, 'store']);
    Route::delete('/games/{game}/content-types/{gameContentType}', [GameContentTypeController::class, 'destroy']);
    Route::get('/games/{game}/content-types/{gameContentType}/dimensions', [GameDimensionController::class, 'index']);
    Route::post('/games/{game}/content-types/{gameContentType}/dimensions', [GameDimensionController::class, 'store']);
    Route::post('/games/{game}/content-types/{gameContentType}/dimensions/copy', [GameDimensionController::class, 'copy']);
    Route::patch('/games/{game}/content-types/{gameContentType}/dimensions/{dimension}', [GameDimensionController::class, 'update']);
    Route::delete('/games/{game}/content-types/{gameContentType}/dimensions/{dimension}', [GameDimensionController::class, 'destroy']);
    Route::post('/games/{game}/content-types/{gameContentType}/dimensions/{dimension}/values', [GameDimensionValueController::class, 'store']);
    Route::patch('/games/{game}/content-types/{gameContentType}/dimensions/{dimension}/values/{dimensionValue}', [GameDimensionValueController::class, 'update']);
    Route::delete('/games/{game}/content-types/{gameContentType}/dimensions/{dimension}/values/{dimensionValue}', [GameDimensionValueController::class, 'destroy']);

    Route::get('/content-types', [ContentTypeController::class, 'index']);
    Route::post('/content-types', [ContentTypeController::class, 'store']);
    Route::patch('/content-types/{contentType}', [ContentTypeController::class, 'update']);
    Route::patch('/content-types/{contentType}/toggle-public', [ContentTypeController::class, 'togglePublic']);
    Route::delete('/content-types/{contentType}', [ContentTypeController::class, 'destroy']);
    Route::post('/content-types/import/validate', [ContentTypeController::class, 'validateImport']);
    Route::post('/content-types/import', [ContentTypeController::class, 'import']);
    Route::get('/game-content-types', [ProjectController::class, 'contentTypes']);
    Route::get('/project-owner-options', [ProjectController::class, 'ownerOptions']);
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::get('/projects/{project}', [ProjectController::class, 'show']);
    Route::patch('/projects/{project}', [ProjectController::class, 'update']);

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
