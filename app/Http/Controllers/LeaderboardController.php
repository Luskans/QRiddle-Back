<?php

namespace App\Http\Controllers;

use App\Interfaces\ScoreServiceInterface;
use App\Models\GameSession;
use App\Models\Riddle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LeaderboardController extends Controller
{
    protected $scoreService;

    public function __construct(ScoreServiceInterface $scoreService)
    {
        $this->scoreService = $scoreService;
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        // $period = $request->get('period', 'week');
        $limit = $request->get('limit', 20);
        $offset = $request->get('offset', 0);
        
        // $ranking = $this->scoreService->getRankingByPeriod($period, $limit, $offset);
        // $userRanking = $this->scoreService->getUserRankByPeriod($period, $user->id);
        $ranking = $this->scoreService->getAggregateRanking($limit, $offset);
        $userRank = $this->scoreService->getAggregateUserRank($user->id);


        return response()->json([
            'ranking' => $ranking,
            'userRank' => $userRank,
            // 'ranking' => [
            //     'week' => $weeklyRanking,
            //     'month' => $monthlyRanking,
            //     'all' => $allRanking
            // ],
            // 'userRank' => [
            //     'week' => $userWeeklyRanking,
            //     'month' => $userMonthlyRanking,
            //     'all' => $userAllRanking
            // ]
        ], Response::HTTP_OK);
    }

    // public function index(Request $request): JsonResponse
    // {
    //     $user = $request->user();
    //     $period = $request->get('period', 'week');
    //     $limit = $request->get('limit') ? (int) $request->get('limit') : null;
    //     $offset = (int) $request->get('offset', 0);
    //     $searchName = $request->get('name');

    //     if ($searchName) {
    //         $ranking = $this->scoreService->getPlayerRankByPeriodAndName($period, $searchName);
    //     } else {
    //         $ranking = $this->scoreService->getRankingByPeriod($period, $limit, $offset);
    //     }

    //     // Récupérer notamment le rang de l'utilisateur
    //     $userRanking = $this->scoreService->getUserRankByPeriod($period, $user->id);

    //     return response()->json([
    //         'ranking' => $ranking,
    //         'userRanking' => $userRanking,
    //     ], Response::HTTP_OK);
    // }

    // public function showByRiddle($riddleId): JsonResponse
    // {
    //     $leaderboard = DB::table('game_scores')
    //         ->join('users', 'game_scores.player_id', '=', 'users.id')
    //         ->select('game_scores.player_id', 'users.username', 'game_scores.score')
    //         ->where('game_scores.riddle_id', $riddleId)
    //         ->orderByDesc('game_scores.score')
    //         ->get();
        
    //     return response()->json([
    //         'riddle_id'   => $riddleId,
    //         'leaderboard' => $leaderboard,
    //     ], Response::HTTP_OK);
    // }

    public function showByRiddle(Riddle $riddle, Request $request)
    {
        // --- Validation de la pagination ---
        $validated = $request->validate([
            'limit' => 'sometimes|integer|min:1|max:100',
            'offset' => 'sometimes|integer|min:0',
        ]);

        $limit = $validated['limit'] ?? 20; // Default limit
        $offset = $validated['offset'] ?? 0; // Default offset

        // --- Requête pour le classement paginé ---
        // Sélectionner seulement les sessions terminées ('completed' ou statut équivalent)
        $leaderboardQuery = GameSession::with('player:id,name,image') // Eager load player info (select specific columns)
            ->where('riddle_id', $riddle->id)
            ->where('status', 'completed') // **IMPORTANT: Assurez-vous que ce statut est correct**
            ->whereNotNull('score') // S'assurer qu'il y a un score
            ->orderByDesc('score'); // Trier par score décroissant

        // Cloner la requête pour obtenir le total avant d'appliquer limit/offset
        $totalQuery = clone $leaderboardQuery;
        $totalCount = $totalQuery->count();

        // Appliquer la pagination
        $leaderboard = $leaderboardQuery->skip($offset)
                                        ->take($limit)
                                        ->get(['id', 'player_id', 'score', 'updated_at']); // Sélectionner les colonnes nécessaires

        // --- Requête pour le rang de l'utilisateur connecté ---
        $userId = Auth::id();
        $userRankData = null;

        if ($userId) {
            // Trouver le meilleur score de l'utilisateur pour cette énigme
            $userBestSession = GameSession::where('riddle_id', $riddle->id)
                ->where('player_id', $userId)
                ->where('status', 'completed')
                ->whereNotNull('score')
                ->orderByDesc('score')
                ->orderBy('updated_at', 'asc')
                ->first(['score']); // Obtenir seulement le meilleur score

            if ($userBestSession) {
                // Calculer le rang : compter combien ont un meilleur score
                $rank = GameSession::where('riddle_id', $riddle->id)
                    ->where('status', 'completed')
                    ->whereNotNull('score')
                    ->where(function ($query) use ($userBestSession) {
                        // Condition pour un meilleur score OU même score mais terminé plus tôt
                        $query->where('score', '>', $userBestSession->score);
                            // ->orWhere(function ($query) use ($userBestSession) {
                            //     $query->where('score', $userBestSession->score)
                            //           ->where('updated_at', '<', $userBestSession->updated_at);
                            // });
                            // Note: Le départage par temps peut être complexe et dépend de la logique exacte.
                            //       Se baser uniquement sur le score est plus simple pour commencer.
                    })
                    ->count() + 1; // Ajouter 1 car le rang commence à 1

                $userRankData = [
                    'score' => $userBestSession->score,
                    'rank' => $rank,
                ];
            }
        }

        // --- Réponse ---
        return response()->json([
            'leaderboard' => $leaderboard,
            'userRank' => $userRankData,
            'meta' => [
                'offset' => $offset,
                'limit' => $limit,
                'total' => $totalCount,
                'hasMore' => ($offset + count($leaderboard)) < $totalCount,
            ]
        ]);
    }
}
