<?php

namespace App\Http\NewControllers;

use App\Interfaces\GameServiceInterface;
use App\Interfaces\RiddleServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Riddle;
use App\Models\GameSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    protected $riddleService;
	protected $gameService;

	public function __construct(RiddleServiceInterface $riddleService, GameServiceInterface $gameService)
    {
		$this->riddleService = $riddleService;
		$this->gameService = $gameService;
	}

    /**
     * Get the paginated list of riddles created by the authenticated user.
     *
     * @param Request  $request
     * @return JsonResponse
     */
    public function myCreatedRiddles(Request $request): JsonResponse
    {
        $userId = Auth::id();

        $validated = $request->validate([
            'page' => 'sometimes|integer|min:1',
            'limit' => 'sometimes|integer|min:1|max:100',
        ]);

        $page = $validated['page'] ?? 1;
        $limit = $validated['limit'] ?? 20;
        $offset = ($page - 1) * $limit;

        $query = Riddle::query()
            ->select(['id', 'title', 'status', 'is_private', 'updated_at', 'latitude', 'longitude'])
            ->where('creator_id', $userId)
            ->orderBy('updated_at', 'desc');

        $totalQuery = clone $query;
        $totalCount = $totalQuery->count();
        $totalPages = ceil($totalCount / $limit);

        $riddles = $query->skip($offset)
            ->take($limit)
            ->get();

        return response()->json([
            'items' => $riddles,
            'page' => $page,
            'limit' => $limit,
            'totalItems' => $totalCount,
            'totalPages' => $totalPages,
            'hasMore' => $page < $totalPages,
        ], Response::HTTP_OK);
    }

    /**
     * Get the paginated list of game sessions played by the authenticated user.
     *
     * @param Request  $request
     * @return JsonResponse
     */
    public function myGameSessions(Request $request): JsonResponse
    {
        $userId = Auth::id();

        $validated = $request->validate([
            'page' => 'sometimes|integer|min:1',
            'limit' => 'sometimes|integer|min:1|max:100',
        ]);

        $page = $validated['page'] ?? 1;
        $limit = $validated['limit'] ?? 20;
        $offset = ($page - 1) * $limit;

        $query = GameSession::query()
            ->select(['id', 'status', 'created_at'])
            ->where('player_id', $userId)
            // ->where('status', '!=', 'active')
            ->orderBy('created_at', 'desc')
            ->with('riddle:id,title,latitude,longitude');

        $totalQuery = clone $query;
        $totalCount = $totalQuery->count();
        $totalPages = ceil($totalCount / $limit);

        $gameSessions = $query->skip($offset)
            ->take($limit)
            ->get();

        return response()->json([
            'items' => $gameSessions,
            'page' => $page,
            'limit' => $limit,
            'totalItems' => $totalCount,
            'totalPages' => $totalPages,
            'hasMore' => $page < $totalPages,
        ], Response::HTTP_OK);
    }

    // /**
    //  * Update authenticated user's profil.
    //  *
    //  * @param Request  $request
    //  * @return JsonResponse
    //  */
    // public function update(Request $request): JsonResponse
    // {
    //     $user = Auth::user();

    //     $validated = $request->validate([
    //         'name' => 'sometimes|required|string|max:255',

    //         // 'image' => 'sometimes|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
    //     ]);

    //     // Gérer l'upload d'image si fourni
    //     // if ($request->hasFile('image')) {
    //     //     // Supprimer l'ancienne image si elle existe et n'est pas l'image par défaut
    //     //     if ($user->image && $user->image !== '/default/user.webp') {
    //     //         Storage::disk('public')->delete(str_replace('/storage', '', $user->image));
    //     //     }
    //     //     $path = $request->file('image')->store('profile_images', 'public');
    //     //     $validated['image'] = Storage::url($path); // Stocker l'URL publique
    //     // }

    //     $user->update($validated);

    //     return response()->json($user, Response::HTTP_OK);
    // }

    /**
     * Get the count of created and played riddles by the authenticated user, and the game session if active.
     *
     * @param Request  $request
     * @return JsonResponse
     */
    public function myHome(Request $request): JsonResponse
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