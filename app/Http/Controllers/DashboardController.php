<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Http\Request;
use App\Interfaces\GameServiceInterface;
use App\Interfaces\RiddleServiceInterface;
use App\Interfaces\ScoreServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
	protected $riddleService;
	protected $gameService;
	protected $scoreService;

	public function __construct(
		RiddleServiceInterface $riddleService,
		GameServiceInterface $gameService,
		ScoreServiceInterface $scoreService
	) {
		$this->riddleService = $riddleService;
		$this->gameService = $gameService;
		$this->scoreService = $scoreService;
	}

	public function index(Request $request): JsonResponse
	{
		$userId = Auth::id();

		if (!$userId) {
			return response()->json(['message' => 'Utilisateur non authentifié.'], Response::HTTP_UNAUTHORIZED);
		}

		// Définir la limite pour l'aperçu du classement
		$leaderboardLimit = 5; // Afficher le Top 5

		try {
			$createdRiddlesCount = $this->riddleService->getCreatedCount($userId);
			$playedGamesCount = $this->gameService->getPlayedCount($userId);
			$activeGameSession = $this->gameService->getActiveSession($userId);
			$leaderboardSnippet = $this->scoreService->getAggregateRanking($leaderboardLimit);

			$data = [
				'created_riddles_count' => $createdRiddlesCount,
				'played_games_count' => $playedGamesCount,
				'active_session' => $activeGameSession,
				'leaderboard_snippet' => $leaderboardSnippet,
			];

			return response()->json($data, Response::HTTP_OK);
			
		} catch (\Exception $e) {
			Log::error('Dashboard data fetching error for user ' . $userId . ': ' . $e->getMessage());

			return response()->json(['message' => 'Erreur de server.'], Response::HTTP_INTERNAL_SERVER_ERROR);
		}
	}
}
