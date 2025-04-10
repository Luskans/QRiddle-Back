<?php

namespace App\Http\Controllers;

use App\Interfaces\GameServiceInterface;
use App\Models\GameSession;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;

class GameSessionController extends Controller
{
    protected $gameService;

    public function __construct(GameServiceInterface $gameService)
    {
        $this->gameService = $gameService;
    }

    /**
     * Affiche la liste des sessions de jeu de l'utilisateur authentifié.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $sessions = GameSession::where('player_id', $user->id)->orderByDesc('created_at')->get();
        
        return response()->json($sessions, Response::HTTP_OK);
    }

    /**
     * Démarre une nouvelle session de jeu pour une énigme donnée.
     *
     * Attendu dans la requête : riddle_id, start_time
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'riddle_id'  => 'required|exists:riddles,id',
            'start_time' => 'required|date',
        ]);
        
        // On associe le player_id à l'utilisateur authentifié.
        $validated['player_id'] = $request->user()->id;
        
        // Par défaut, le status est 'active' et score est null.
        $validated['status'] = 'active';
        $session = GameSession::create($validated);
        
        return response()->json($session, Response::HTTP_CREATED);
    }

    /**
     * Affiche les détails d'une session de jeu.
     */
    public function show(GameSession $gameSession)
    {
        // Optionnel: vérifier que l'utilisateur authentifié est bien le propriétaire de la session.
        // $this->authorize('view', $gameSession);
        
        return response()->json($gameSession, Response::HTTP_OK);
    }

    /**
     * Met à jour une session de jeu (par exemple, la terminer ou l'abandonner).
     */
    public function update(Request $request, GameSession $gameSession)
    {
        // On peut vérifier la propriété !
        // $this->authorize('update', $gameSession);
        
        $validated = $request->validate([
            'status'    => 'required|in:active,completed,abandoned',
            'end_time'  => 'nullable|date', // en cas de fin demandée
            'score'     => 'nullable|integer|min:0',
        ]);
        
        $gameSession->update($validated);
        
        return response()->json($gameSession, Response::HTTP_OK);
    }

    /**
     * Supprime (ou annule) une session de jeu.
     */
    public function destroy(GameSession $gameSession)
    {
        // Vérification de l'autorisation, si besoin, par exemple.
        // $this->authorize('delete', $gameSession);
        
        $gameSession->delete();
        
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Affiche les détails de la session de jeu active.
     */
    public function getActiveRiddle(Request $request): JsonResponse
    {
        $user = $request->user();
        $activeGameSession = $this->gameService->getActiveRiddle($user->id);

        return response()->json([
            'gameSession' => $activeGameSession
        ], Response::HTTP_OK);
    }
}
