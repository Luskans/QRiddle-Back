<?php

// use Illuminate\Http\Request;
// use Illuminate\Support\Facades\Route;
// use App\Http\Controllers\AuthController;
// use App\Http\Controllers\GameController;
// use App\Http\Controllers\GameSessionController;
// use App\Http\Controllers\StepController;
// use App\Http\Controllers\CommentController;
// use App\Http\Controllers\HintController;
// use App\Http\Controllers\HomeController;
// use App\Http\Controllers\LeaderboardController;
// use App\Http\Controllers\ReviewController;
// use App\Http\Controllers\RiddleController;
// use App\Http\Controllers\SessionStepController;

// // Route::get('/user', function (Request $request) {
// //     return $request->user();
// // })->middleware('auth:sanctum');

// // Routes publiques
// Route::post('/register', [AuthController::class, 'register']);
// Route::post('/login', [AuthController::class, 'login']);

// // Nouvel agencement
// Route::middleware('auth:sanctum')->group(function () {
//     Route::post('/logout', [AuthController::class, 'logout']);
//     Route::get('/user', [AuthController::class, 'user']);
    
//     // Home
//     Route::get('/', [HomeController::class, 'index']);

//     // Riddles
//     Route::apiResource('riddles', RiddleController::class);
//     Route::get('/riddles/created/list', [RiddleController::class, 'getCreatedList']);
//     // Route::get('/riddles/{riddleId}', [RiddleController::class, 'getRiddleDetail']);
    
//     // Etapes imbriquées dans les riddles
//     Route::apiResource('riddles.steps', StepController::class);
    
//     // Hints imbriqués dans les étapes
//     Route::apiResource('steps.hints', HintController::class);
    
//     // Reviews sur les riddles
//     Route::apiResource('riddles.reviews', ReviewController::class);
    
//     // Sessions de jeu
//     Route::apiResource('game-sessions', GameSessionController::class);
    
//     // Etapes de session (peut-être imbriquées sous game-sessions)
//     Route::apiResource('game-sessions.session-steps', SessionStepController::class);
    
//     // Classement
//     Route::get('/leaderboard', [LeaderboardController::class, 'index']);
//     Route::get('/leaderboard/search', [LeaderboardController::class, 'search']);
//     Route::get('/leaderboard/riddle/{riddleId}', [LeaderboardController::class, 'showByRiddle']);
// });









// use Illuminate\Http\Request;
// use Illuminate\Support\Facades\Route;
// use App\Http\Controllers\AuthController;
// use App\Http\Controllers\GameController;
// use App\Http\Controllers\GameSessionController;
// use App\Http\Controllers\StepController;
// use App\Http\Controllers\CommentController;
// use App\Http\Controllers\HintController;
// use App\Http\Controllers\HomeController;
// use App\Http\Controllers\LeaderboardController;
// use App\Http\Controllers\ReviewController;
// use App\Http\Controllers\RiddleController;
// use App\Http\Controllers\SessionStepController;


// // Routes publiques
// Route::post('/register', [AuthController::class, 'register']);
// Route::post('/login', [AuthController::class, 'login']);

// // Routes authentifiées
// Route::middleware('auth:sanctum')->group(function () {
//     Route::post('/logout', [AuthController::class, 'logout']);
//     Route::get('/user', [AuthController::class, 'user']);
    
//     // Home
//     Route::get('/', [HomeController::class, 'index']);

//     // Riddles
//     Route::apiResource('riddles', RiddleController::class);
//     Route::get('/riddles/created/list', [RiddleController::class, 'getCreatedList']);
//     Route::get('/riddles/participated/list', [RiddleController::class, 'getParticipatedList']);
//     Route::get('/riddles/{riddle}/leaderboard', [LeaderboardController::class, 'showByRiddle']);

//     // Steps
//     Route::apiResource('riddles.steps', StepController::class);
    
//     // Hints
//     Route::apiResource('steps.hints', HintController::class);
    
//     // Reviews
//     Route::apiResource('riddles.reviews', ReviewController::class);
    
//     // Game session
//     Route::apiResource('game-session', GameSessionController::class);
    
//     // Game session steps
//     Route::apiResource('game-session.session-steps', SessionStepController::class);
    
//     // Global leaderboard
//     Route::get('/leaderboard', [LeaderboardController::class, 'index']);
// });



















use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController; // Renommé depuis HomeController
use App\Http\Controllers\GameSessionController;
use App\Http\Controllers\HintController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\RiddleController;
use App\Http\Controllers\SessionStepController;
use App\Http\Controllers\StepController;
use App\Http\Controllers\UserController; // Pour les ressources liées à l'utilisateur

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// --- Routes Publiques ---
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// --- Routes Authentifiées ---
Route::middleware('auth:sanctum')->group(function () {

    // == Authentification & Utilisateur ==
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']); // Obtenir les infos de l'utilisateur connecté
    // Peut-être ajouter PUT /user pour la mise à jour du profil (UserController@updateProfile)

    // == Dashboard (Ancien Home) ==
    Route::get('/dashboard', [DashboardController::class, 'index'])
         ->name('dashboard.index'); // Nommer la route est une bonne pratique

    // == Ressources Utilisateur ('Mes ...') ==
    Route::prefix('users/me')->name('users.me.')->group(function () {
        // Mes énigmes créées
        Route::get('/riddles/created', [UserController::class, 'myCreatedRiddles'])
             ->name('riddles.created'); // GET /users/me/riddles/created

        // Mes sessions de jeu (parties jouées/en cours)
        Route::get('/game-sessions', [UserController::class, 'myGameSessions'])
             ->name('game-sessions.index'); // GET /users/me/game-sessions
             // Ajouter des filtres ?status=completed, ?status=in_progress via query params

        // Mon rang global (si nécessaire séparément)
        Route::get('/leaderboard/global', [LeaderboardController::class, 'getGlobalUserRank'])
             ->name('leaderboard.global.user'); // GET /users/me/leaderboard/global
    });

    // == Énigmes (Riddles) ==
    Route::apiResource('riddles', RiddleController::class);
    // apiResource crée:
    // GET /riddles -> RiddleController@index (liste toutes les énigmes publiques, avec filtres/pagination)
    // POST /riddles -> RiddleController@store (créer une énigme)
    // GET /riddles/{riddle} -> RiddleController@show (détail d'une énigme)
    // PUT/PATCH /riddles/{riddle} -> RiddleController@update (màj une énigme)
    // DELETE /riddles/{riddle} -> RiddleController@destroy (supprimer une énigme)

    // == Étapes (Steps) - Imbriquées sous Riddles ==
    Route::apiResource('riddles.steps', StepController::class)->shallow();
    // Crée principalement:
    // GET /riddles/{riddle}/steps -> StepController@index (liste les étapes d'une énigme)
    // POST /riddles/{riddle}/steps -> StepController@store (créer une étape pour une énigme)
    // GET /steps/{step} -> StepController@show (détail d'une étape - grâce à shallow)
    // PUT/PATCH /steps/{step} -> StepController@update (màj une étape - grâce à shallow)
    // DELETE /steps/{step} -> StepController@destroy (supprimer une étape - grâce à shallow)

    // == Indices (Hints) - Imbriqués sous Steps ==
    Route::apiResource('steps.hints', HintController::class)->shallow();
    // Crée principalement:
    // GET /steps/{step}/hints -> HintController@index
    // POST /steps/{step}/hints -> HintController@store
    // GET /hints/{hint} -> HintController@show
    // PUT/PATCH /hints/{hint} -> HintController@update
    // DELETE /hints/{hint} -> HintController@destroy

    // == Avis (Reviews) - Imbriqués sous Riddles ==
    Route::apiResource('riddles.reviews', ReviewController::class)->shallow();
    // Crée principalement:
    // GET /riddles/{riddle}/reviews -> ReviewController@index (liste les avis d'une énigme)
    // POST /riddles/{riddle}/reviews -> ReviewController@store (créer un avis pour une énigme)
    // GET /reviews/{review} -> ReviewController@show
    // PUT/PATCH /reviews/{review} -> ReviewController@update (si l'auteur peut modifier)
    // DELETE /reviews/{review} -> ReviewController@destroy (si l'auteur/admin peut supprimer)

    // == Sessions de Jeu (Game Sessions) ==
    // Renommé en pluriel 'game-sessions'
    Route::apiResource('game-sessions', GameSessionController::class)->except(['index']); // On utilise /users/me/game-sessions pour la liste perso
    // Crée principalement:
    // POST /game-sessions -> GameSessionController@store (Démarrer une nouvelle partie pour un riddle_id donné dans le body)
    // GET /game-sessions/{game_session} -> GameSessionController@show (Détail d'une session en cours ou terminée)
    // PUT/PATCH /game-sessions/{game_session} -> GameSessionController@update (Ex: abandonner une partie)
    // DELETE /game-sessions/{game_session} -> GameSessionController@destroy (Si suppression autorisée)

    // == Étapes de Session (Session Steps) - Actions pendant le jeu ==
    // Renommé parent en pluriel 'game-sessions'
    Route::apiResource('game-sessions.session-steps', SessionStepController::class)->only(['index', 'show']);
    // GET /game-sessions/{game_session}/session-steps -> SessionStepController@index (Historique des étapes pour cette session)
    // GET /game-sessions/{game_session}/session-steps/{session_step} -> SessionStepController@show (Détail d'une étape de session)

    // Actions spécifiques pendant le jeu (pas du CRUD standard)
    Route::post('/game-sessions/{game_session}/steps/validate', [SessionStepController::class, 'validateStep'])
         ->name('game-sessions.steps.validate'); // POST pour valider un QR code scanné (envoyer qr_code dans le body)
    Route::post('/game-sessions/{game_session}/steps/{step}/hints/use', [SessionStepController::class, 'useHint'])
         ->name('game-sessions.steps.hints.use'); // POST pour enregistrer l'utilisation d'un indice

    // == Classements (Leaderboards) ==
    Route::prefix('leaderboards')->name('leaderboards.')->group(function () {
        // Classement Global
        Route::get('/global', [LeaderboardController::class, 'showGlobal'])
             ->name('global.show'); // GET /leaderboards/global?period=week|month|all&limit=..&offset=..

        // Classement par Énigme
        Route::get('/riddles/{riddle}', [LeaderboardController::class, 'showByRiddle'])
             ->name('riddle.show'); // GET /leaderboards/riddles/{riddle}?limit=..&offset=..
    });

});