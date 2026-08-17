<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\ConversationParticipantController;
use App\Http\Controllers\FriendshipController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
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
    Route::post('profile/avatar', [ProfileController::class, 'uploadAvatar']);
    Route::delete('profile/avatar', [ProfileController::class, 'deleteAvatar']);

    Route::get('users/search', [UserController::class, 'search']);

    Route::get('conversations', [ConversationController::class, 'index']);
    Route::post('conversations', [ConversationController::class, 'store']);
    Route::get('conversations/{conversation}', [ConversationController::class, 'show']);
    Route::post('conversations/{conversation}/read', [ConversationController::class, 'markAsRead']);

    Route::get('conversations/{conversation}/messages', [MessageController::class, 'index']);
    Route::post('conversations/{conversation}/messages', [MessageController::class, 'store']);
    Route::put('conversations/{conversation}/messages/{message}', [MessageController::class, 'update']);
    Route::delete('conversations/{conversation}/messages/{message}', [MessageController::class, 'destroy']);

    Route::get('conversations/{conversation}/participants', [ConversationParticipantController::class, 'index']);
    Route::post('conversations/{conversation}/participants', [ConversationParticipantController::class, 'store']);
    Route::delete('conversations/{conversation}/participants/{user}', [ConversationParticipantController::class, 'destroy']);
    Route::put('conversations/{conversation}/participants/{user}/role', [ConversationParticipantController::class, 'updateRole']);

    Route::get('friends', [FriendshipController::class, 'index']);
    Route::get('friends/requests', [FriendshipController::class, 'incomingRequests']);
    Route::get('friends/requests/sent', [FriendshipController::class, 'sentRequests']);
    Route::get('friends/blocked', [FriendshipController::class, 'blockedUsers']);
    Route::post('friends/requests', [FriendshipController::class, 'store']);
    Route::post('friends/requests/{friendship}/accept', [FriendshipController::class, 'accept']);
    Route::delete('friends/requests/{friendship}', [FriendshipController::class, 'destroyRequest']);
    Route::delete('friends/{friendship}', [FriendshipController::class, 'destroy']);
    Route::post('friends/block/{user}', [FriendshipController::class, 'block']);
    Route::delete('friends/block/{friendship}', [FriendshipController::class, 'unblock']);
});