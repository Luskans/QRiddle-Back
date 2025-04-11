<?php

namespace App\Http\Controllers;

use App\Models\GameSession;
use App\Models\Riddle; // Importer Riddle
use App\Interfaces\GameServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule; // Pour la validation du statut

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
        $userId = Auth::id();
        if (!$userId) {
            return response()->json(['message' => 'Unauthenticated.'], Response::HTTP_UNAUTHORIZED);
        }

        // 1. Valider l'ID de l'énigme reçue dans le corps de la requête
        $validated = $request->validate([
            'riddle_id' => 'required|integer|exists:riddles,id', // Vérifie que l'énigme existe
        ]);

        $riddleId = $validated['riddle_id'];

        // 2. Appeler le service pour démarrer la session
        // Le service gérera la vérification (partie déjà en cours ?),
        // la création de GameSession, la création de la première SessionStep, etc.
        try {
            $result = $this->gameService->startSession($userId, $riddleId);

            // Le service pourrait retourner différentes choses :
            // - La nouvelle session + première étape
            // - Une erreur si une session est déjà en cours
            // - Une erreur si l'énigme n'est pas jouable

            // Exemple de gestion de la réponse du service
            if (isset($result['error'])) {
                // Gérer les erreurs métier spécifiques retournées par le service
                $statusCode = $result['status_code'] ?? Response::HTTP_BAD_REQUEST;
                return response()->json(['message' => $result['error']], $statusCode);
            }

            // Si succès, retourner les données de la session démarrée
            // (par exemple, la session et l'étape actuelle)
            return response()->json($result, Response::HTTP_CREATED); // 201 Created

        } catch (\Exception $e) {
            Log::error("Error starting game session for user {$userId}, riddle {$riddleId}: " . $e->getMessage());
            return response()->json(['message' => 'Failed to start game session.'], Response::HTTP_INTERNAL_SERVER_ERROR);
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
        // 1. Autorisation : Vérifier si l'utilisateur connecté est le joueur de cette session
        if (Auth::id() !== $gameSession->player_id) {
            // Ou si c'est le créateur de l'énigme ? À définir selon tes règles.
            // if (Auth::id() !== $gameSession->riddle->creator_id) { ... }
            return response()->json(['message' => 'Unauthorized.'], Response::HTTP_FORBIDDEN);
        }

        // 2. Charger les relations nécessaires pour l'affichage
        //    (Ex: énigme, étape actuelle, historique des étapes)
        try {
            $gameSession->load([
                'riddle:id,title,creator_id', // Infos de base de l'énigme
                'player:id,name,image', // Infos du joueur
                // Charger l'étape de session actuellement active (si la session est active)
                'sessionSteps' => function ($query) use ($gameSession) {
                    if ($gameSession->status === 'active') { // Ou 'in_progress'
                        $query->where('status', 'active') // Statut de l'étape de session
                              ->with('step:id,order_number,latitude,longitude') // Charger infos de l'étape de base
                              ->latest('start_time') // Prend la plus récente si plusieurs actives (ne devrait pas arriver)
                              ->limit(1);
                    } else {
                        // Si la session est terminée/abandonnée, on peut charger toutes les étapes
                        $query->with('step:id,order_number')->orderBy('order_number');
                    }
                }
            ]);

            // Renommer la relation pour plus de clarté dans la réponse JSON
            if ($gameSession->relationLoaded('sessionSteps') && $gameSession->status === 'active') {
                 $gameSession->current_step = $gameSession->sessionSteps->first();
                 unset($gameSession->sessionSteps); // Enlever la collection si on ne veut que l'étape actuelle
            }


            return response()->json($gameSession, Response::HTTP_OK);

        } catch (\Exception $e) {
             Log::error("Error showing game session {$gameSession->id}: " . $e->getMessage());
            return response()->json(['message' => 'Failed to retrieve game session details.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
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
}