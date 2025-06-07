<?php

namespace App\Http\Controllers;

use App\Models\Riddle;
use App\Services\Interfaces\ScoreServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class LeaderboardController extends Controller
{
    protected $scoreService;

    public function __construct(ScoreServiceInterface $scoreService)
    {
        $this->scoreService = $scoreService;
    }

    /**
     * Get the paginated list of global ranking by period.
     *
     * @param Request  $request
     * @return JsonResponse
     */
    public function getGlobalRanking(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period' => ['required', Rule::in(['week', 'month', 'all'])],
            'page' => 'sometimes|integer|min:1',
            'limit' => 'sometimes|integer|min:1|max:100',
        ]);

        $userId = $request->user()->id;
        $period = $validated['period'];
        $page = $validated['page'] ?? 1;
        $limit = $validated['limit'] ?? 20;

        try {
            $result = $this->scoreService->getGlobalRanking($period, $page, $limit, $userId);
            return response()->json($result, Response::HTTP_OK);
            
        } catch (\Exception $e) {
            Log::error("Error fetching global ranking: " . $e->getMessage());
            return response()->json(['message' => 'Erreur serveur lors de la récupération du classement global.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get the paginated list of riddle ranking.
     *
     * @param Riddle  $riddle
     * @param Request  $request
     * @return JsonResponse
     */
    public function getRiddleRanking(Riddle $riddle, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => 'sometimes|integer|min:1',
            'limit' => 'sometimes|integer|min:1|max:100',
        ]);

        $userId = $request->user()->id;
        $page = $validated['page'] ?? 1;
        $limit = $validated['limit'] ?? 20;

        try {
            $result = $this->scoreService->getRiddleRanking($riddle, $page, $limit, $userId);
            return response()->json($result, Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error("Error fetching riddle ranking for riddle {$riddle->id}: " . $e->getMessage());
            return response()->json(['message' => 'Erreur serveur lors de la récupération du classement de l\'énigme.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get the top 5 of global ranking by period.
     *
     * @param Request  $request
     * @return JsonResponse
     */
    public function getTopGlobalRanking(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period' => 'required|in:week,month,all',
        ]);

        $period = $validated['period'];
        $userId = $request->user()->id;

        try {
            $result = $this->scoreService->getTopGlobalRanking($period, $userId);
            return response()->json($result, Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error("Error fetching top global ranking: " . $e->getMessage());
            return response()->json(['message' => 'Erreur serveur lors de la récupération du top du classement global.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get the top 5 list of riddle ranking.
     *
     * @param Riddle  $riddle
     * @param Request  $request
     * @return JsonResponse
     */
    public function getTopRiddleRanking(Riddle $riddle, Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        try {
            $result = $this->scoreService->getTopRiddleRanking($riddle, $userId);
            return response()->json($result, Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error("Error fetching top riddle ranking for riddle {$riddle->id}: " . $e->getMessage());
            return response()->json(['message' => 'Erreur serveur lors de la récupération du top du classement de l\'énigme.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}