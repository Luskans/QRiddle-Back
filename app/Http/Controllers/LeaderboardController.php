<?php

namespace App\Http\Controllers;

use App\Interfaces\ScoreServiceInterface;
use App\Models\Riddle;
use App\Models\GameSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class LeaderboardController extends Controller
{
    protected $scoreService;

    public function __construct(ScoreServiceInterface $scoreService)
    {
        $this->scoreService = $scoreService;
    }

    public function showGlobal(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period' => 'required|in:week,month,all',
            'limit' => 'sometimes|integer|min:1|max:100',
            'offset' => 'sometimes|integer|min:0',
        ]);

        $period = $validated['period'];
        $limit = $validated['limit'] ?? 20;
        $offset = $validated['offset'] ?? 0;

        try {
            $result = $this->scoreService->getGlobalRankingByPeriod($period, $limit, $offset);

            return response()->json([
                'ranking' => $result['ranking'] ?? [],
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Error fetching global leaderboards: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur lors de la récupération du classement.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function showByRiddle(Riddle $riddle, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'limit' => 'sometimes|integer|min:1|max:100',
            'offset' => 'sometimes|integer|min:0',
        ]);

        $limit = $validated['limit'] ?? 20;
        $offset = $validated['offset'] ?? 0;
        $userId = Auth::id();

        try {
            $ranking = $this->scoreService->getRankingByRiddle($riddle, $limit, $offset);
            $userRank = $this->scoreService->getUserRankByRiddle($riddle, $userId);

            return response()->json([
                'ranking' => $ranking['ranking'] ?? [],
                'userRank' => $userRank ?? null
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error("Error fetching leaderboard for riddle {$riddle->id}: " . $e->getMessage());
            return response()->json(['message' => 'Erreur lors de la récupération du classement de l\'énigme.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getGlobalUserRank(Request $request): JsonResponse
    {
        $userId = Auth::id();
        if (!$userId) {
            return response()->json(['message' => 'Utilisateur non authentifié.'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $userRanks = $this->scoreService->getAggregateGlobalUserRank($userId);
            return response()->json($userRanks, Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Error fetching global user rank for user ' . $userId . ': ' . $e->getMessage());
            return response()->json(['message' => 'Erreur lors de la récupération de votre classement.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // public function showByRiddle(Riddle $riddle, Request $request): JsonResponse
    // {
    //     // --- Validation de la pagination ---
    //     $validated = $request->validate([
    //         'limit' => 'sometimes|integer|min:1|max:100',
    //         'offset' => 'sometimes|integer|min:0',
    //     ]);

    //     $limit = $validated['limit'] ?? 20; // Limite par défaut
    //     $offset = $validated['offset'] ?? 0; // Offset par défaut

    //     try {
    //         $result = $this->scoreService->getRankingByRiddle($riddle, $limit, $offset);

    //         // --- Requête pour le classement paginé ---
    //         $leaderboardQuery = GameSession::with('player:id,name,image') // Charger infos joueur
    //             ->where('riddle_id', $riddle->id)
    //             ->where('status', 'completed') // **IMPORTANT: Vérifier ce statut**
    //             ->whereNotNull('score')
    //             ->orderByDesc('score')
    //             ->orderBy('updated_at', 'asc'); // Critère secondaire (temps de complétion)

    //         // Cloner pour compter le total avant limit/offset
    //         $totalQuery = clone $leaderboardQuery;
    //         $totalCount = $totalQuery->count();

    //         // Appliquer la pagination
    //         $leaderboard = $leaderboardQuery->skip($offset)
    //                                         ->take($limit)
    //                                         // Sélectionner les colonnes nécessaires pour la réponse
    //                                         ->get(['id', 'player_id', 'score', 'updated_at']);

    //         // --- Requête pour le rang de l'utilisateur connecté ---
    //         $userId = Auth::id();
    //         $userRankData = null;

    //         if ($userId) {
    //             // Trouver le meilleur score de l'utilisateur pour cette énigme
    //             $userBestSession = GameSession::where('riddle_id', $riddle->id)
    //                 ->where('player_id', $userId)
    //                 ->where('status', 'completed')
    //                 ->whereNotNull('score')
    //                 ->orderByDesc('score')
    //                 ->orderBy('updated_at', 'asc')
    //                 ->first(['score', 'updated_at']); // Garder updated_at si besoin de départage

    //             if ($userBestSession) {
    //                 // Calculer le rang : compter combien ont un meilleur score
    //                 $rank = GameSession::where('riddle_id', $riddle->id)
    //                     ->where('status', 'completed')
    //                     ->whereNotNull('score')
    //                     ->where(function ($query) use ($userBestSession) {
    //                         // Condition pour un meilleur score
    //                         $query->where('score', '>', $userBestSession->score);
    //                         // Optionnel: Ajouter départage par temps si scores égaux
    //                         // ->orWhere(function ($q) use ($userBestSession) {
    //                         //     $q->where('score', $userBestSession->score)
    //                         //       ->where('updated_at', '<', $userBestSession->updated_at);
    //                         // });
    //                     })
    //                     ->count() + 1; // Ajouter 1 car le rang commence à 1

    //                 $userRankData = [
    //                     'score' => $userBestSession->score,
    //                     'rank' => $rank,
    //                 ];
    //             }
    //         }

    //         // --- Réponse ---
    //         return response()->json([
    //             'leaderboard' => $leaderboard,
    //             'userRank' => $userRankData, // Sera null si l'utilisateur n'a pas joué/fini
    //             'meta' => [
    //                 'offset' => $offset,
    //                 'limit' => $limit,
    //                 'total' => $totalCount,
    //                 'hasMore' => ($offset + count($leaderboard)) < $totalCount,
    //             ]
    //         ], Response::HTTP_OK);

    //     } catch (\Exception $e) {
    //         Log::error("Error fetching leaderboard for riddle {$riddle->id}: " . $e->getMessage());
    //         return response()->json(['message' => 'Failed to retrieve riddle leaderboard.'], Response::HTTP_INTERNAL_SERVER_ERROR);
    //     }
    // }
}