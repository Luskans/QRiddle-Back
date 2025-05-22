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

    // TODO : séparer ranking et user rank ? évite le refetch avec infinite scroll

    /**
     * Get the paginated list of global ranks by period.
     *
     * @param Request  $request
     * @return JsonResponse
     */
    public function getGlobalRanking(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period' => 'required|in:week,month,all',
            'page' => 'sometimes|integer|min:1',
            'limit' => 'sometimes|integer|min:1|max:100',
        ]);

        $period = $validated['period'];
        $page = $validated['page'] ?? 1;
        $limit = $validated['limit'] ?? 20;
        $offset = ($page - 1) * $limit;

        $query = GlobalScore::query()
            ->select(['id', 'score'])
            ->where('period', $period)
            ->orderBy('score', 'desc')
            ->with('user:id,name,image');

        $totalQuery = clone $query;
        $totalCount = $totalQuery->count();
        $totalPages = ceil($totalCount / $limit);

        $globalRanks = $query->skip($offset)
            ->take($limit)
            ->get();

        $user = Auth::user();

        $userScore = GlobalScore::query()
            ->select(['id', 'score'])
            ->where('user_id', $user)
            ->where('period', $period)
            ->first();

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
     * Get the paginated list of riddle ranks.
     *
     * @param Request  $request
     * @return JsonResponse
     */
    public function getRiddleRanking(Riddle $riddle, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => 'sometimes|integer|min:1',
            'limit' => 'sometimes|integer|min:1|max:100',
        ]);

        $page = $validated['page'] ?? 1;
        $limit = $validated['limit'] ?? 20;
        $offset = ($page - 1) * $limit;

        $query = GameSession::query()
            ->select(['id', 'score'])
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

        $user = Auth::user();

        $userScore = GameSession::query()
            ->select(['id', 'score'])
            ->where('riddle_id', $riddle->id)
            ->where('user_id', $user)
            ->where('status', 'completed')
            ->first();

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
}