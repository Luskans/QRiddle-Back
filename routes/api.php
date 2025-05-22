<?php

use Illuminate\Support\Facades\Route;
use App\Http\NewControllers\AuthController;
use App\Http\NewControllers\GameController;
use App\Http\NewControllers\HintController;
use App\Http\NewControllers\LeaderboardController;
use App\Http\NewControllers\ReviewController;
use App\Http\NewControllers\RiddleController;
use App\Http\NewControllers\StepController;
use App\Http\NewControllers\UserController;



// --- Public routes ---
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

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

     Route::apiResource('riddles.steps', StepController::class)->except(['index'])->shallow();
     
     Route::apiResource('steps.hints', HintController::class)->except(['index', 'show'])->shallow();
     
     Route::apiResource('riddles.reviews', ReviewController::class)->except(['show'])->shallow();
     
     Route::prefix('game')->name('game.')->group(function () {
          Route::get('/{game-session}', [GameController::class, 'getSession'])->name('session');
          Route::post('/{game-session}/validate-step', [GameController::class, 'validateStep'])->name('validate-step');
          Route::post('/{game-session}/unlock-hint', [GameController::class, 'unlockHint'])->name('unlock-hint');
          Route::patch('/{game-session}', [GameController::class, 'abandonSession'])->name('abandon-session');
     });

     Route::prefix('leaderboards')->name('leaderboards.')->group(function () {
        Route::get('/global', [LeaderboardController::class, 'getGlobalRanking'])->name('global');
        Route::get('/riddles/{riddle}', [LeaderboardController::class, 'getRiddleRanking'])->name('riddle');
    });
});












