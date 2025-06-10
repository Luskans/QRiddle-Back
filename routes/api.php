<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\HintController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\RiddleController;
use App\Http\Controllers\StepController;
use App\Http\Controllers\UserController;


// --- Public routes ---
Route::middleware('throttle:10,1')->group(function () {
     Route::post('/register', [AuthController::class, 'register']);
     Route::post('/login', [AuthController::class, 'login']);
});

// --- Authenticated routes ---
Route::middleware('auth:sanctum')->group(function () {
     
     Route::post('/logout', [AuthController::class, 'logout']);
     Route::get('/user', [AuthController::class, 'user']);
          
     Route::prefix('users/me')->name('users.me.')->group(function () {
          Route::get('/riddles/created', [UserController::class, 'myCreatedRiddles'])->name('riddles.created');
          Route::get('/game-sessions', [UserController::class, 'myGameSessions'])->name('game-sessions.index');
          Route::get('/home', [UserController::class, 'myHome'])->name('game-sessions.active');
     });
     
     Route::apiResource('riddles', RiddleController::class);
     Route::get('/riddles/{riddle}/session', [RiddleController::class, 'getSessionByRiddle'])->name('riddles.session');
     Route::post('/riddles/{riddle}/play', [GameController::class, 'playRiddle'])->name('riddles.play');
     Route::get('/riddles/{riddle}/reviews/top', [ReviewController::class, 'getTopReviewsByRiddle'])->name('riddles.reviews-top');

     Route::apiResource('riddles.steps', StepController::class)->except(['index'])->shallow();
     
     Route::apiResource('steps.hints', HintController::class)->except(['index', 'show'])->shallow();
     // Route::post('/hints/{hint}/upload-image',  [HintController::class, 'uploadImage'])->name('hints.upload-image');
     
     Route::apiResource('riddles.reviews', ReviewController::class)->except(['show'])->shallow();
     
     Route::prefix('game')->name('game.')->group(function () {
          Route::get('/{gameSession}', [GameController::class, 'getActiveSession'])->name('active-session');
          Route::post('/{gameSession}/validate-step', [GameController::class, 'validateStep'])->name('validate-step');
          Route::post('/{gameSession}/unlock-hint', [GameController::class, 'unlockHint'])->name('unlock-hint');
          Route::get('/{gameSession}/complete', [GameController::class, 'getCompletedSession'])->name('completed-session');
          Route::patch('/{gameSession}', [GameController::class, 'abandonSession'])->name('abandon-session');
     });

     Route::prefix('leaderboards')->name('leaderboards.')->group(function () {
          Route::get('/global', [LeaderboardController::class, 'getGlobalRanking'])->name('global');
          Route::get('/global/top', [LeaderboardController::class, 'getTopGlobalRanking'])->name('global-top');
          Route::get('/riddles/{riddle}', [LeaderboardController::class, 'getRiddleRanking'])->name('riddle');
          Route::get('/riddles/{riddle}/top', [LeaderboardController::class, 'getTopRiddleRanking'])->name('riddle-top');
    });
});












