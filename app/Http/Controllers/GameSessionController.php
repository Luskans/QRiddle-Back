<?php

namespace App\Http\Controllers;

use App\Models\GameSession;
use App\Models\Riddle; // Importer Riddle
use App\Interfaces\GameServiceInterface;
use App\Models\SessionStep;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule; // Pour la validation du statut
use Illuminate\Support\Facades\DB;

class GameSessionController extends Controller
{
    protected $gameService;

    public function __construct(GameServiceInterface $gameService)
    {
        $this->gameService = $gameService;
    }

    /**
     * Démarre une nouvelle session de jeu pour une énigme donnée.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        // $userId = Auth::id();
        // if (!$userId) {
        //     return response()->json(['message' => 'Unauthenticated.'], Response::HTTP_UNAUTHORIZED);
        // }

        // // 1. Valider l'ID de l'énigme reçue dans le corps de la requête
        // $validated = $request->validate([
        //     'riddle_id' => 'required|integer|exists:riddles,id', // Vérifie que l'énigme existe
        // ]);

        // $riddleId = $validated['riddle_id'];

        // // 2. Appeler le service pour démarrer la session
        // // Le service gérera la vérification (partie déjà en cours ?),
        // // la création de GameSession, la création de la première SessionStep, etc.
        // try {
        //     $result = $this->gameService->startSession($userId, $riddleId);

        //     // Le service pourrait retourner différentes choses :
        //     // - La nouvelle session + première étape
        //     // - Une erreur si une session est déjà en cours
        //     // - Une erreur si l'énigme n'est pas jouable

        //     // Exemple de gestion de la réponse du service
        //     if (isset($result['error'])) {
        //         // Gérer les erreurs métier spécifiques retournées par le service
        //         $statusCode = $result['status_code'] ?? Response::HTTP_BAD_REQUEST;
        //         return response()->json(['message' => $result['error']], $statusCode);
        //     }

        //     // Si succès, retourner les données de la session démarrée
        //     // (par exemple, la session et l'étape actuelle)
        //     return response()->json($result, Response::HTTP_CREATED); // 201 Created

        // } catch (\Exception $e) {
        //     Log::error("Error starting game session for user {$userId}, riddle {$riddleId}: " . $e->getMessage());
        //     return response()->json(['message' => 'Failed to start game session.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        // }





        $userId = Auth::id();
        if (!$userId) {
            return response()->json(['message' => 'Utilisateur non authentifié.'], Response::HTTP_UNAUTHORIZED);
        }

        $validatedData = $request->validate([
            'riddle_id' => 'required|integer|exists:riddles,id',
            'password' => 'nullable|string|max:255'
        ]);
        $riddleId = $validatedData['riddle_id'];

        try {
            $riddle = Riddle::find($riddleId);
            
            // Vérifie si l'énigme existe et publiée
            if (!$riddle || $riddle->status !== 'published') {
                return response()->json(['message' => 'Cette énigme n\'est pas disponible actuellement.'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            // Vérifie si l'énigme a au moins une étape et un indice
            $firstStep= $riddle->steps->first();
            if (!$firstStep) {
                return response()->json(['message' => 'Cette énigme n\'a pas encore d\'étape.'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            // Vérifie si l'énigme a au moins un indice
            $firstHint = $firstStep->hints->first();
            if (!$firstHint) {
                return response()->json(['message' => 'Cette énigme n\'a pas encore d\'indice.'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            // Vérifie si le mot de passe est correct si l'énigme est privée
            if ($riddle->is_private && $riddle->password !== $validatedData['password']) {
                return response()->json(['message' => 'Le mot de passe est incorrect.'], Response::HTTP_FORBIDDEN);
            }
            
            // Abandonne les GameSessions en cours si il y en a
            DB::transaction(function () use ($userId) {
                $activeSessions = GameSession::where('player_id', $userId)
                    ->where('status', 'active')
                    ->get();

                foreach ($activeSessions as $session) {
                    $session->status = 'abandoned';
                    $session->save();
                }
            });

            $gameSession = DB::transaction(function () use ($userId, $riddle, $firstStep) {
                // Créer la GameSession
                $gameSession = GameSession::create([
                    'riddle_id' => $riddle->id,
                    'player_id' => $userId,
                    'status' => 'active',
                    'score' => 0,
                ]);

                // Créer la première SessionStep
                $sessionStep = SessionStep::create([
                    'game_session_id' => $gameSession->id,
                    'step_id' => $firstStep->id,
                    'status' => 'active',
                    'start_time' => Carbon::now(),
                    'hint_used_number' => 0,
                ]);

                return $gameSession;

                // // Recharger les relations nécessaires pour la réponse
                // // $gameSession->load('riddle:id,title');
                // // $sessionStep->load('step:id,order_number,latitude,longitude'); // Charger l'étape de base
                // $sessionStep->load([
                //     'step' => function ($query) {
                //         $query->select('id', 'order_number')
                //             ->with([
                //                 'hints' => function ($q) {
                //                     $q->select('id', 'step_id', 'order_number')
                //                         ->orderBy('order_number');
                //                 }
                //             ])
                //             ->orderBy('order_number');
                //     }
                // ]);

                // return [
                //     'session' => $gameSession,
                //     'current_step' => $sessionStep,
                //     'hints' => [$firstHint]
                //     // Optionnel: retourner les indices disponibles pour la première étape
                //     // 'available_hints' => $firstStep->hints()->where('order_number', 1)->get() ?? []
                // ];
            });

            return response()->json($gameSession, Response::HTTP_CREATED);

        } catch (\Exception $e) {
            Log::error('Error creating step: ' . $e->getMessage());
            return response()->json(['message' => 'Échec lors de la création de la partie.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Affiche les détails d'une session de jeu spécifique.
     *
     * @param  \App\Models\GameSession  $gameSession // Liaison de modèle implicite
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(GameSession $gameSession): JsonResponse
    {
        // // 1. Autorisation : Vérifier si l'utilisateur connecté est le joueur de cette session
        // if (Auth::id() !== $gameSession->player_id) {
        //     // Ou si c'est le créateur de l'énigme ? À définir selon tes règles.
        //     // if (Auth::id() !== $gameSession->riddle->creator_id) { ... }
        //     return response()->json(['message' => 'Unauthorized.'], Response::HTTP_FORBIDDEN);
        // }

        // // 2. Charger les relations nécessaires pour l'affichage
        // //    (Ex: énigme, étape actuelle, historique des étapes)
        // try {
        //     $gameSession->load([
        //         'riddle:id,title,creator_id', // Infos de base de l'énigme
        //         'player:id,name,image', // Infos du joueur
        //         // Charger l'étape de session actuellement active (si la session est active)
        //         'sessionSteps' => function ($query) use ($gameSession) {
        //             if ($gameSession->status === 'active') { // Ou 'in_progress'
        //                 $query->where('status', 'active') // Statut de l'étape de session
        //                       ->with('step:id,order_number,latitude,longitude') // Charger infos de l'étape de base
        //                       ->latest('start_time') // Prend la plus récente si plusieurs actives (ne devrait pas arriver)
        //                       ->limit(1);
        //             } else {
        //                 // Si la session est terminée/abandonnée, on peut charger toutes les étapes
        //                 $query->with('step:id,order_number')->orderBy('order_number');
        //             }
        //         }
        //     ]);

        //     // Renommer la relation pour plus de clarté dans la réponse JSON
        //     if ($gameSession->relationLoaded('sessionSteps') && $gameSession->status === 'active') {
        //          $gameSession->current_step = $gameSession->sessionSteps->first();
        //          unset($gameSession->sessionSteps); // Enlever la collection si on ne veut que l'étape actuelle
        //     }


        //     return response()->json($gameSession, Response::HTTP_OK);

        // } catch (\Exception $e) {
        //      Log::error("Error showing game session {$gameSession->id}: " . $e->getMessage());
        //     return response()->json(['message' => 'Failed to retrieve game session details.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        // }





        if (Auth::id() !== $gameSession->player_id) {
            return response()->json(['message' => 'Utilisateur non autorisé.'], Response::HTTP_FORBIDDEN);
        }

        if ($gameSession->status !== 'active') {
            return response()->json(['message' => 'La partie est déjà terminée ou abandonnée.'], Response::HTTP_BAD_REQUEST);
        }

        $sessionStep = $gameSession->latestActiveSessionStep;

        if (!$sessionStep) {
            return response()->json(['message' => 'L\'étape est déjà terminée ou abandonnée.'], Response::HTTP_NOT_FOUND);
        }

        $stepCount = $gameSession->riddle()->steps()->count();

        $step = $sessionStep->step()->with(['hints' => function($query) {
            $query->orderBy('order_number', 'asc');
        }])->first();
        
        
        if (!$step) {
            return response()->json(['message' => 'Aucune étape touvée.'], Response::HTTP_NOT_FOUND);
        }

        $hints = $step->hints->map(function($hint, $index) use ($sessionStep) {
            return [
                'id' => $hint->id,
                'order_number' => $hint->order_number,
                'type' => $hint->type,
                'content' => $hint->content,
                'unlocked' => $hint->order_number <= $sessionStep->hint_used_number + 1
            ];
        });

        return response()->json([
            'session_step' => $sessionStep->only(['id', 'hint_used_number', 'start_time']),
            'step' => $step->only(['id', 'order_number']),
            'step_count' => $stepCount,
            'hints' => $hints,
        ], Response::HTTP_OK);
    }

    /**
     * Met à jour une session de jeu (ex: pour l'abandonner).
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\GameSession  $gameSession
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, GameSession $gameSession): JsonResponse
    {
        // 1. Autorisation
        if (Auth::id() !== $gameSession->player_id) {
            return response()->json(['message' => 'Unauthorized.'], Response::HTTP_FORBIDDEN);
        }

        // 2. Validation : On permet seulement de changer le statut pour 'abandoned' ici
        $validated = $request->validate([
            'status' => ['required', Rule::in(['abandoned'])], // Seul 'abandoned' est autorisé via cette route
        ]);

        // 3. Vérifier si la session est déjà terminée ou abandonnée
        if ($gameSession->status === 'completed' || $gameSession->status === 'abandoned') {
             return response()->json(['message' => 'Game session is already finished or abandoned.'], Response::HTTP_BAD_REQUEST);
        }

        // 4. Appeler le service pour gérer l'abandon (peut-être mettre à jour l'étape active aussi)
        try {
            $updatedSession = $this->gameService->abandonSession($gameSession); // Méthode à ajouter au service

            return response()->json($updatedSession, Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error("Error updating game session {$gameSession->id}: " . $e->getMessage());
            return response()->json(['message' => 'Failed to update game session.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Supprime une session de jeu (si autorisé).
     * Attention: Supprime l'historique.
     *
     * @param  \App\Models\GameSession  $gameSession
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(GameSession $gameSession): JsonResponse
    {
        // 1. Autorisation (Joueur ? Admin ?)
        if (Auth::id() !== $gameSession->player_id) {
            // Peut-être seul un admin peut supprimer ?
            return response()->json(['message' => 'Unauthorized.'], Response::HTTP_FORBIDDEN);
        }

        // 2. Logique de suppression
        try {
            $gameSession->delete(); // Supprime la session et potentiellement les SessionSteps en cascade (selon DB)
            return response()->json(null, Response::HTTP_NO_CONTENT); // 204 No Content

        } catch (\Exception $e) {
            Log::error("Error deleting game session {$gameSession->id}: " . $e->getMessage());
            return response()->json(['message' => 'Failed to delete game session.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getGameSessionByRiddle(Riddle $riddle): JsonResponse
    {
        // Récupérer l'utilisateur connecté
        // $user = $request->user();
        $userId = Auth::id();
        if (!$userId) {
            return response()->json(['message' => 'Utilisateur non authentifié.'], Response::HTTP_UNAUTHORIZED);
        }

        // Recherche de la session active pour ce riddle et cet utilisateur
        // Vous pouvez adapter la condition (par exemple, choisir "active" ou "in_progress")
        // $gameSession = GameSession::with(['sessionSteps' => function ($query) {
        //         // Charger la dernière étape active (ou toutes les étapes si nécessaire)
        //         $query->where('status', 'active')->latest('start_time');
        //     }, 'riddle:id,title'])
        //     ->where('riddle_id', $riddle->id)
        //     ->where('player_id', $user->id)
        //     ->where('status', 'active') // Vous pouvez aussi autoriser 'in_progress' selon votre logique
        //     ->latest('created_at')
        //     ->first();

        $gameSession = GameSession::select('id','status', 'riddle_id', 'player_id')
                                ->where('riddle_id', $riddle->id)
                                ->where('player_id', $userId)
                                ->with('sessionSteps:id,game_session_id,status,start_time,end_time')
                                ->first();

        // if (!$gameSession) {
        //     return response()->json([
        //         'message' => 'Aucune session de jeu en cours pour cette énigme.'
        //     ], Response::HTTP_NOT_FOUND);
        // }

        // return response()->json($gameSession, Response::HTTP_OK);
        return response()->json([
            'session' => $gameSession
        ], Response::HTTP_OK);
    }

    /**
     * Unlock a new hint for the current active step
     *
     * @param Request $request
     * @param GameSession $gameSession
     * @return \Illuminate\Http\JsonResponse
     */
    public function unlockHint(Request $request, GameSession $gameSession)
    {
        // Vérifier que l'utilisateur est bien le joueur de cette session
        if (Auth::id() !== $gameSession->player_id) {
            return response()->json(['message' => 'Utilisateur non autorisé'], Response::HTTP_FORBIDDEN);
        }
        
        // Récupérer la session_step active
        $sessionStep = $gameSession->latestActiveSessionStep;
        
        if (!$sessionStep) {
            return response()->json(['message' => 'No active step found'], Response::HTTP_NOT_FOUND);
        }
        
        $validatedData = $request->validate([
            'hint_order_number' => 'required|number',
        ]);

        if ($validatedData['hint_order_number'] <= $sessionStep->hint_used_number) {
            return response()->json(['message' => 'Indice déjà dévérouillé.'], Response::HTTP_BAD_REQUEST);
        } 

        if ($sessionStep->hint_used_number - $validatedData['hint_order_number'] > 1) {
            return response()->json(['message' => 'Veuillez dévérouiller l\'indice précédent.'], Response::HTTP_BAD_REQUEST);
        }
        
        // Incrémenter le nombre d'indices utilisés
        $sessionStep->hint_used_number += 1;
        $sessionStep->save();
        
        // Retourner les données mises à jour
        return $this->getActiveGame($gameSession);
    }
}