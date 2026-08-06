<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {

    Route::middleware('throttle:10,1')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
        Route::post('google', [AuthController::class, 'google']);
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::put('profile', [ProfileController::class, 'update']);
    Route::delete('profile', [ProfileController::class, 'destroy']);

    Route::get('conversations', [ConversationController::class, 'index']);
    Route::post('conversations', [ConversationController::class, 'store']);
    Route::get('conversations/{conversation}', [ConversationController::class, 'show']);
    Route::post('conversations/{conversation}/read', [ConversationController::class, 'markAsRead']);

    Route::get('conversations/{conversation}/messages', [MessageController::class, 'index']);
    Route::post('conversations/{conversation}/messages', [MessageController::class, 'store']);
    Route::put('conversations/{conversation}/messages/{message}', [MessageController::class, 'update']);
    Route::delete('conversations/{conversation}/messages/{message}', [MessageController::class, 'destroy']);
});