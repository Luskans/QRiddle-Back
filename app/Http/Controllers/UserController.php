<?php

namespace App\Http\Controllers;

use App\Interfaces\GameSessionServiceInterface;
use App\Interfaces\RiddleServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    protected $riddleService;
    protected $gameSessionService;

    public function __construct(
        RiddleServiceInterface $riddleService,
        GameSessionServiceInterface $gameSessionService,
    ) {
        $this->riddleService = $riddleService;
        $this->gameSessionService = $gameSessionService;
    }

    /**
     * Get the paginated list of riddles created by the authenticated user.
     *
     * @param Request  $request
     * @return JsonResponse
     */
    public function myCreatedRiddles(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $validated = $request->validate([
            'page' => 'sometimes|integer|min:1',
            'limit' => 'sometimes|integer|min:1|max:100',
        ]);

        $page = $validated['page'] ?? 1;
        $limit = $validated['limit'] ?? 20;

        try {
            $result = $this->riddleService->getCreatedRiddles($userId, $page, $limit);
            
            return response()->json($result, Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error("Error fetching created riddles for user {$userId}: " . $e->getMessage());
            return response()->json(['message' => 'Erreur serveur lors de la récupération des énigmes créées.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get the paginated list of game sessions played by the authenticated user.
     *
     * @param Request  $request
     * @return JsonResponse
     */
    public function myGameSessions(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $validated = $request->validate([
            'page' => 'sometimes|integer|min:1',
            'limit' => 'sometimes|integer|min:1|max:100',
        ]);

        $page = $validated['page'] ?? 1;
        $limit = $validated['limit'] ?? 20;

        try {
            $result = $this->gameSessionService->getPlayedGameSessions($userId, $page, $limit);
            
            return response()->json($result, Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error("Error fetching game sessions for user {$userId}: " . $e->getMessage());
            return response()->json(['message' => 'Erreur serveur lors de la récupération des parties jouées.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update authenticated user's profile.
     *
     * @param Request  $request
     * @return JsonResponse
     */
    // public function update(Request $request): JsonResponse
    // {
    //     $validated = $request->validate([
    //         'name' => 'sometimes|required|string|max:255',
    //         'description' => 'sometimes|nullable|string|max:1000',
    //         'image' => 'sometimes|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
    //     ]);

    //     try {
    //         $user = $this->userService->updateProfile($request->user(), $validated, $request->file('image'));
            
    //         return response()->json([
    //             'data' => $user,
    //         ], Response::HTTP_OK);
    //     } catch (\Exception $e) {
    //         Log::error("Error updating user profile: " . $e->getMessage());
    //         return response()->json(['message' => 'Erreur serveur lors de la mise à jour du profil.'], Response::HTTP_INTERNAL_SERVER_ERROR);
    //     }
    // }

    /**
     * Get the count of created and played riddles by the authenticated user, and the game session if active.
     *
     * @param Request  $request
     * @return JsonResponse
     */
    public function myHome(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        try {
            $createdRiddlesCount = $this->riddleService->getCreatedCount($userId);
            $playedGamesCount = $this->gameSessionService->getPlayedCount($userId);
            $activeGameSession = $this->gameSessionService->getHomeActiveSession($userId);

            $data = [
                'createdCount' => $createdRiddlesCount,
                'playedCount' => $playedGamesCount,
                'activeGameSession' => $activeGameSession,
            ];

            return response()->json($data, Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error("Home data fetching error for user {$userId}: " . $e->getMessage());
            return response()->json(['message' => 'Erreur serveur lors de la récupération des données de l\'accueil.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}