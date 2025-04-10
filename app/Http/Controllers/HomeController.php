<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Http\Request;
use App\Interfaces\GameServiceInterface;
use App\Interfaces\RiddleServiceInterface;
use App\Interfaces\ScoreServiceInterface;
use Illuminate\Http\JsonResponse;

class HomeController extends Controller
{
    protected $gameService;
    protected $riddleService;
    protected $scoreService;

    public function __construct(GameServiceInterface $gameService, RiddleServiceInterface $riddleService, ScoreServiceInterface $scoreService)
    {
        $this->gameService = $gameService;
        $this->riddleService = $riddleService;
        $this->scoreService = $scoreService;
    }

    public function index(Request $request): JsonResponse
    {
        // L'utilisateur authentifié
        $user = $request->user();

        // 1. Notifications non lues
        $notificationsCount = 4;

        // 2. Riddle en cours
        $activeRiddle = $this->gameService->getActiveRiddle($user->id);

        // 3. Nombre d'énigmes auxquelles l'utilisateur a participé
        $participatedCount = $this->gameService->getParticipatedCount($user->id);

        // 4. Nombre d'énigmes créées par l'utilisateur
        $createdCount = $this->riddleService->getCreatedCount($user->id);

        // // 5. Classement top 5 et utilisateur
        $ranking = $this->scoreService->getAggregateRanking(5);
        $userRank = $this->scoreService->getAggregateUserRank($user->id);

        return response()->json([
            'notificationsCount' => $notificationsCount,
            'activeRiddle' => $activeRiddle,
            'participatedCount' => $participatedCount,
            'createdCount' => $createdCount,
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
    // TODO: enlever les requètes et résultats liés au leaderboard du HomeControler et utiliser le leaderboardController
}
