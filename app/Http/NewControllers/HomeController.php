<?php

namespace App\Http\NewControllers;

use Illuminate\Http\Response;
use Illuminate\Http\Request;
use App\Interfaces\GameServiceInterface;
use App\Interfaces\RiddleServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class HomeController extends Controller
{
	protected $riddleService;
	protected $gameService;

	public function __construct(
		RiddleServiceInterface $riddleService,
		GameServiceInterface $gameService,
	) {
		$this->riddleService = $riddleService;
		$this->gameService = $gameService;
	}

	public function index(Request $request): JsonResponse
	{
		$userId = Auth::id();

		if (!$userId) {
			return response()->json(['message' => 'Utilisateur non authentifié.'], Response::HTTP_UNAUTHORIZED);
		}

		try {
			$createdRiddlesCount = $this->riddleService->getCreatedCount($userId);
			$playedGamesCount = $this->gameService->getPlayedCount($userId);
			$activeGameSession = $this->gameService->getActiveSession($userId);

			$data = [
				'createdCount' => $createdRiddlesCount,
				'playedCount' => $playedGamesCount,
				'activeGameSession' => $activeGameSession,
			];

			return response()->json($data, Response::HTTP_OK);
			
		} catch (\Exception $e) {
			Log::error("Home data fetching error for user {$userId}: " . $e->getMessage());

			return response()->json(['message' => 'Erreur serveur.'], Response::HTTP_INTERNAL_SERVER_ERROR);
		}
	}
}
