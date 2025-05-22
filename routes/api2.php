<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\GameSessionController;
use App\Http\Controllers\HintController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\RiddleController;
use App\Http\Controllers\SessionStepController;
use App\Http\Controllers\StepController;
use App\Http\Controllers\UserController;



// --- Public routes ---
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// --- Authenticated routes ---
Route::middleware('auth:sanctum')->group(function () {

    // == Authentification & Utilisateur ==
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']); // Obtenir les infos de l'utilisateur connecté
    // Peut-être ajouter PUT /user pour la mise à jour du profil (UserController@updateProfile)

    // == Dashboard (Ancien Home) ==
    Route::get('/home', [HomeController::class, 'index'])
         ->name('home.index'); // Nommer la route est une bonne pratique

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

        // Mon rang global (si nécessaire séparément)
        Route::get('/leaderboard/{riddle}', [LeaderboardController::class, 'getGlobalUserRank'])
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

    Route::get('/riddles/{riddle}/my-session', [GameSessionController::class, 'getGameSessionByRiddle'])
     ->name('riddles.my-session');

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

     // faire ses propres routes :
     // Route::get('/game/{game_session}', [GameController::class, 'getActiveGame']);
     // Route::post('/game', [GameController::class, 'createGame']);
     // Route::post('/game/{game_session}/unlock-hint', [GameController::class, 'unlockHint'])->name('game.active-game.unlock-hint');
     // Route::post('/game/{game_session}/validate-step', [GameController::class, 'validateStep'])->name('game.active-game.validate-step');
     // Route::put('/game/{game_session}', [GameController::class, 'abandonGame']);


//     // Récupérer la session_step active avec les indices -> remplacer par game-sessions/{game-sessions]}
//     Route::get('/game-sessions/{game_session}/active-step/hints', [SessionStepController::class, 'getActiveWithHints'])
//          ->name('game-sessions.active-step.hints');
    
//     // Déverrouiller un nouvel indice -> remplacer par game-sessions/{game-sessions}/unlock-hint
//     Route::post('/game-sessions/{game_session}/active-step/unlock-hint', [SessionStepController::class, 'unlockHint'])
//          ->name('game-sessions.active-step.unlock-hint');























// --- Public routes ---
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// --- Authenticated routes ---
Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    Route::get('/home', [HomeController::class, 'index'])->name('home.index');

    Route::prefix('users/me')->name('users.me.')->group(function () {
         Route::get('/riddles/created', [UserController::class, 'myCreatedRiddles']);
         Route::get('/game-sessions', [UserController::class, 'myGameSessions']);
          Route::put('/{user}', [UserController::class, 'update']);
          Route::patch('/{user}', [UserController::class, 'update']);
    });

    Route::prefix('riddles')->name('riddles.')->group(function () {
          Route::get('/', [RiddleController::class, 'index']);
          Route::post('/', [RiddleController::class, 'store']);
          Route::get('/{riddle}', [RiddleController::class, 'show']);
          Route::put('/{riddle}', [RiddleController::class, 'update']);
          Route::patch('/{riddle}', [RiddleController::class, 'update']);
          Route::delete('/{riddle}', [RiddleController::class, 'destroy']);
          Route::get('/{riddle}/my-session', [GameController::class, 'myGameSessionByRiddle']);
          Route::get('/{riddle}/steps', [StepController::class, 'index']);
          Route::post('/{riddle}/steps', [StepController::class, 'store']);
          Route::get('/{riddle}/reviews', [ReviewController::class, 'index']);
          Route::post('/{riddle}/reviews', [ReviewController::class, 'store']);
    });

    Route::prefix('steps')->name('steps.')->group(function () {
          Route::get('/{step}', [StepController::class, 'show']);
          Route::put('/{step}', [StepController::class, 'update']);
          Route::patch('/{step}', [StepController::class, 'update']);
          Route::delete('/{step}', [StepController::class, 'destroy']);
          Route::get('/{step}/hints', [HintController::class, 'index']);
          Route::post('/{step}/hints', [HintController::class, 'store']);
    });

    Route::prefix('hints')->name('hints.')->group(function () {
          Route::get('/{hint}', [HintController::class, 'show']);
          Route::put('/{hint}', [HintController::class, 'update']);
          Route::patch('/{hint}', [HintController::class, 'update']);
          Route::delete('/{hint}', [HintController::class, 'destroy']);
    });

    Route::prefix('reviews')->name('reviews.')->group(function () {
          Route::get('/{review}', [ReviewController::class, 'show']);
          Route::put('/{review}', [ReviewController::class, 'update']);
          Route::patch('/{review}', [ReviewController::class, 'update']);
          Route::delete('/{review}', [ReviewController::class, 'destroy']);
    });

     Route::prefix('game')->name('game.')->group(function () {
          Route::post('/', [GameController::class, 'newSession']);
          Route::get('/{game_session}', [GameController::class, 'myActiveSession']);
          Route::post('/{game_session}/validate-step', [GameController::class, 'validateStep']);
          Route::post('/{game_session}/unlock-hint', [GameController::class, 'unlockHint']);
          Route::put('/{game_session}', [GameController::class, 'abandonSession']);
          Route::patch('/{game_session}', [GameController::class, 'abandonSession']);
     });

     Route::prefix('leaderboards')->name('leaderboards.')->group(function () {
        Route::get('/global', [LeaderboardController::class, 'showGlobal'])->name('global.show');
        Route::get('/riddles/{riddle}', [LeaderboardController::class, 'showByRiddle'])->name('riddle.show');
    });
});








// --- Public routes ---
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// --- Authenticated routes ---
Route::middleware('auth:sanctum')->group(function () {
     Route::apiResource('riddles', RiddleController::class);
     Route::get('/riddles/{riddle}/my-session', [GameSessionController::class, 'getGameSessionByRiddle'])->name('riddles.my-session');
     Route::apiResource('riddles.steps', StepController::class)->shallow();
     Route::apiResource('steps.hints', HintController::class)->shallow();
     Route::apiResource('riddles.reviews', ReviewController::class)->shallow();
     Route::apiResource('game-sessions', GameSessionController::class)->except(['index']);
     Route::prefix('game-sessions')->name('game-sessions.')->group(function () {
          Route::post('/game-sessions/{game_session}/steps/validate', [SessionStepController::class, 'validateStep'])->name('game-sessions.steps.validate');
          Route::post('/game-sessions/{game_session}/steps/{step}/hints/use', [SessionStepController::class, 'useHint'])->name('game-sessions.steps.hints.use');
     });
     Route::prefix('leaderboards')->name('leaderboards.')->group(function () {
          Route::get('/global', [LeaderboardController::class, 'showGlobal'])->name('global.show');
          Route::get('/riddles/{riddle}', [LeaderboardController::class, 'showByRiddle'])->name('riddle.show');
     });
});

    // == Énigmes (RiddlesReview
    Route::apiResource('riddles', RiddleController::class);
    // apiResource crée:
    // GET /riddles -> RiddleController@index (liste toutes les énigmes publiques, avec filtres/pagination)
    // POST /riddles -> RiddleController@store (créer une énigme)
    // GET /riddles/{riddle} -> RiddleController@show (détail d'une énigme)
    // PUT/PATCH /riddles/{riddle} -> RiddleController@update (màj une énigme)
    // DELETE /riddles/{riddle} -> RiddleController@destroy (supprimer une énigme)

    Route::get('/riddles/{riddle}/my-session', [GameSessionController::class, 'getGameSessionByRiddle'])
     ->name('riddles.my-session');

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


     // faire ses propres routes :
     // Route::get('/game/{game_session}', [GameController::class, 'getActiveGame']);
     // Route::post('/game', [GameController::class, 'createGame']);
     // Route::post('/game/{game_session}/unlock-hint', [GameController::class, 'unlockHint'])->name('game.active-game.unlock-hint');
     // Route::post('/game/{game_session}/validate-step', [GameController::class, 'validateStep'])->name('game.active-game.validate-step');
     // Route::put('/game/{game_session}', [GameController::class, 'abandonGame']);


//     // Récupérer la session_step active avec les indices -> remplacer par game-sessions/{game-sessions]}
//     Route::get('/game-sessions/{game_session}/active-step/hints', [SessionStepController::class, 'getActiveWithHints'])
//          ->name('game-sessions.active-step.hints');
    
//     // Déverrouiller un nouvel indice -> remplacer par game-sessions/{game-sessions}/unlock-hint
//     Route::post('/game-sessions/{game_session}/active-step/unlock-hint', [SessionStepController::class, 'unlockHint'])
//          ->name('game-sessions.active-step.unlock-hint');













