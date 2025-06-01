<?php

namespace App\Http\NewControllers;

use App\Interfaces\ScoreServiceInterface;
use App\Models\Riddle;
use App\Models\GameSession;
use App\Models\GlobalScore;
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

    /**
     * Get the paginated list of global ranking by period.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getGlobalRanking(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period' => 'required|in:week,month,all',
            'page' => 'sometimes|integer|min:1',
            'limit' => 'sometimes|integer|min:1|max:100',
        ]);

        $userId = $request->user()->id;
        $period = $validated['period'];
        $page = $validated['page'] ?? 1;
        $limit = $validated['limit'] ?? 20;
        $offset = ($page - 1) * $limit;

        $query = GlobalScore::query()
            ->select(['id', 'user_id', 'score'])
            ->where('period', $period)
            ->orderBy('score', 'desc')
            ->with('user:id,name,image');

        $totalQuery = clone $query;
        $totalCount = $totalQuery->count();
        $totalPages = ceil($totalCount / $limit);

        $globalRanks = $query->skip($offset)
            ->take($limit)
            ->get();

        $userScore = GlobalScore::query()
            ->select(['id', 'score'])
            ->where('user_id', $userId)
            ->where('period', $period)
            ->value('score');

        $userRank = null;
        if ($userScore) {
            $userRank = GlobalScore::query()
                ->where('period', $period)
                ->where('score', '>', $userScore)
                ->count() + 1;
        }

        return response()->json([
            'items' => $globalRanks,
            'data' => [
                'userScore' => $userScore,
                'userRank' => $userRank,
            ],
            'page' => $page,
            'limit' => $limit,
            'totalItems' => $totalCount,
            'totalPages' => $totalPages,
            'hasMore' => $page < $totalPages,
        ], Response::HTTP_OK);
    }


    /**
     * Get the paginated list of riddle ranking.
     *
     * @param Request $request
     * @param  \App\Models\Riddle $riddle
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
        $offset = ($page - 1) * $limit;

        $query = GameSession::query()
            ->select(['id', 'user_id', 'score'])
            ->where('riddle_id', $riddle->id)
            ->where('status', 'completed')
            ->orderBy('score', 'desc')
            ->with('user:id,name,image');

        $totalQuery = clone $query;
        $totalCount = $totalQuery->count();
        $totalPages = ceil($totalCount / $limit);

        $riddleRanks = $query->skip($offset)
            ->take($limit)
            ->get();

        $userScore = GameSession::query()
            ->select(['id', 'score'])
            ->where('riddle_id', $riddle->id)
            ->where('user_id', $userId)
            ->where('status', 'completed')
            ->value('score');

        $userRank = null;
        if ($userScore) {
            $userRank = GameSession::query()
                ->where('riddle_id', $riddle->id)
                ->where('score', '>', $userScore)
                ->count() + 1;
        }

        return response()->json([
            'items' => $riddleRanks,
            'data' => [
                'userScore' => $userScore,
                'userRank' => $userRank,
            ],
            'page' => $page,
            'limit' => $limit,
            'totalItems' => $totalCount,
            'totalPages' => $totalPages,
            'hasMore' => $page < $totalPages,
        ], Response::HTTP_OK);
    }


    /**
     * Get the top 5 of global ranking by period.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getTopGlobalRanking(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period' => 'required|in:week,month,all',
        ]);

        $period = $validated['period'];
        $userId = $request->user()->id;

        $globalRanking = GlobalScore::query()
            ->select(['id', 'user_id', 'score'])
            ->where('period', $period)
            ->orderBy('score', 'desc')
            ->with('user:id,name,image')
            ->take(5)
            ->get();

        $userScore = GlobalScore::query()
            ->select(['id', 'score'])
            ->where('user_id', $userId)
            ->where('period', $period)
            ->value('score');

        $userRank = null;
        if ($userScore) {
            $userRank = GlobalScore::query()
                ->where('period', $period)
                ->where('score', '>', $userScore)
                ->count() + 1;
        }

        return response()->json([
            'items' => $globalRanking,
            'data' => [
                'userScore' => $userScore,
                'userRank' => $userRank,
            ],
        ], Response::HTTP_OK);
    }


    /**
     * Get the top 5 list of riddle ranking.
     *
     * @param Request $request
     * @param  \App\Models\Riddle $riddle
     * @return JsonResponse
     */
    public function getTopRiddleRanking(Riddle $riddle, Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $riddleRanking = GameSession::query()
            ->select(['id', 'user_id', 'score'])
            ->where('riddle_id', $riddle->id)
            ->where('status', 'completed')
            ->orderBy('score', 'desc')
            ->with('user:id,name,image')
            ->take(5)
            ->get();

        $userScore = GameSession::query()
            ->select(['id', 'score'])
            ->where('riddle_id', $riddle->id)
            ->where('user_id', $userId)
            ->where('status', 'completed')
            ->value('score');

        $userRank = null;
        if ($userScore) {
            $userRank = GameSession::query()
                ->where('riddle_id', $riddle->id)
                ->where('score', '>', $userScore)
                ->count() + 1;
        }

        return response()->json([
            'items' => $riddleRanking,
            'data' => [
                'userScore' => $userScore,
                'userRank' => $userRank,
            ],
        ], Response::HTTP_OK);
    }

}