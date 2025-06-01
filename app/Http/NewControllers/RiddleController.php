<?php

namespace App\Http\NewControllers;

use App\Http\Resources\RiddleDetailResource;
use App\Models\GameSession;
use App\Models\Riddle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RiddleController extends Controller
{
    /**
     * Get the paginated list of riddles.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    // public function index(Request $request): JsonResponse
    // {
    //     $validated = $request->validate([
    //         'page' => 'sometimes|integer|min:1',
    //         'limit' => 'sometimes|integer|min:1|max:100',
    //     ]);

    //     $page = $validated['page'] ?? 1;
    //     $limit = $validated['limit'] ?? 20;
    //     $offset = ($page - 1) * $limit;

    //     $query = Riddle::query()
    //         ->select(['id', 'title', 'is_private', 'latitude', 'longitude'])
    //         ->where('status', 'active')
    //         ->withCount('steps')
    //         ->withCount('reviews')
    //         ->withAvg('reviews', 'rating')
    //         ->withAvg('reviews', 'difficulty');

    //     $totalQuery = clone $query;
    //     $totalCount = $totalQuery->count();
    //     $totalPages = ceil($totalCount / $limit);

    //     $riddles = $query->skip($offset)
    //         ->take($limit)
    //         ->get();

    //     return response()->json([
    //         'items' => $riddles,
    //         'page' => $page,
    //         'limit' => $limit,
    //         'totalItems' => $totalCount,
    //         'totalPages' => $totalPages,
    //         'hasMore' => $page < $totalPages,
    //     ], Response::HTTP_OK);
    // }
    // TODO : Sans pagination pour l'instant
    public function index(Request $request): JsonResponse
    {
        $riddles = Riddle::query()
            ->select(['id', 'title', 'status', 'is_private', 'updated_at', 'latitude', 'longitude'])
            ->where('status', 'published')
            ->withCount('steps')
            ->withCount('reviews')
            ->withAvg('reviews', 'rating')
            ->withAvg('reviews', 'difficulty')
            ->get();

        return response()->json([
            'items' => $riddles,
        ], Response::HTTP_OK);
    }

    /**
     * Create a new riddle.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:1000',
            'is_private' => 'required|boolean',
            'latitude' => ['required', 'regex:/^[-]?(([0-8]?[0-9])\.(\d+))|(90(\.0+)?)$/'],
            'longitude' => ['required', 'regex:/^[-]?((((1[0-7][0-9])|([0-9]?[0-9]))\.(\d+))|180(\.0+)?)$/'],
        ]);

        if ($validatedData['is_private']) {
            $validatedData['password'] = Str::random(6);
        } else {
            $validatedData['password'] = null;
        }

        try {
            $riddle = $request->user()->createdRiddles()->create($validatedData);

            return response()->json([
                'data' => $riddle,
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            Log::error('Error creating riddle: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur serveur : la création de l\'énigme a échouée.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get the detail of a riddle.
     *
     * @param  \App\Models\Riddle  $riddle
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Riddle $riddle): JsonResponse
    { 
        $riddle->load(['creator:id,name,image', 'steps:id,riddle_id,order_number,qr_code']);
        $riddle->loadCount('steps');
        $riddle->loadCount('reviews');
        $riddle->loadAvg('reviews', 'rating');
        $riddle->loadAvg('reviews', 'difficulty');

        return response()->json([
            'data' => new RiddleDetailResource($riddle),
        ], Response::HTTP_OK);
    }

    /**
     * Update a riddle.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Riddle  $riddle
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Riddle $riddle): JsonResponse
    {
        if (Auth::id() !== $riddle->creator_id) {
            return response()->json(['message' => 'Utilisateur non autorisé.'], Response::HTTP_FORBIDDEN);
        }

        $validatedData = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string|max:1000',
            'is_private' => 'sometimes|required|boolean',
            'status' => ['sometimes', 'required', Rule::in(['draft', 'published', 'disabled'])],
            'latitude' => ['sometimes', 'required', 'regex:/^[-]?(([0-8]?[0-9])\.(\d+))|(90(\.0+)?)$/'],
            'longitude' => ['sometimes', 'required', 'regex:/^[-]?((((1[0-7][0-9])|([0-9]?[0-9]))\.(\d+))|180(\.0+)?)$/'],
        ]);

        if (isset($validatedData['is_private']) && $validatedData['is_private'] === true) {
            $validatedData['password'] = Str::random(6);
        } else if (isset($validatedData['is_private']) && $validatedData['is_private'] === false) {
            $validatedData['password'] = null;
        }

        if (isset($validatedData['status']) && ($validatedData['status'] === 'published' || $validatedData['status'] === 'draft')) {
            if ($riddle->steps()->count() === 0) {
                return response()->json([
                    'message' => 'Impossible de publier une énigme sans au moins une étape.'
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $stepsWithoutHints = $riddle->steps()
                ->whereDoesntHave('hints')
                ->count();

            if ($stepsWithoutHints > 0) {
                return response()->json([
                    'message' => 'Toutes les étapes doivent avoir au moins un indice.'
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        try {
            $riddle->update($validatedData);
            $riddle->refresh();

            return response()->json([
                'data' => $riddle,
            ], Response::HTTP_OK);        

        } catch (\Exception $e) {
            Log::error("Error updating riddle {$riddle->id}: " . $e->getMessage());
            return response()->json(['message' => 'Erreur serveur : la mise à jour de l\'énigme a échouée.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Delete (soft delete) a riddle.
     *
     * @param  \App\Models\Riddle  $riddle
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Riddle $riddle): JsonResponse
    {
        if (Auth::id() !== $riddle->creator_id) {
            return response()->json(['message' => 'Utilisateur non autorisé.'], Response::HTTP_FORBIDDEN);
        }

        try {
            $riddle->delete();

            return response()->json(null, Response::HTTP_NO_CONTENT);

        } catch (\Exception $e) {
            Log::error("Error deleting riddle {$riddle->id}: " . $e->getMessage());
            return response()->json(['message' => 'Erreur serveur : la suppression de l\'énigme a échouée.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get a game session with session steps for a riddle.
     *
     * @param  \App\Models\Riddle
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSessionByRiddle(Riddle $riddle): JsonResponse
    {
        $userId = Auth::id();

        $gameSession = GameSession::select('id','status')
            ->where('riddle_id', $riddle->id)
            ->where('user_id', $userId)
            ->with('sessionSteps:id,game_session_id,status,start_time,end_time')
            ->first();

        if (!$gameSession) {
            return response()->json([
                'message' => 'Aucune partie jouée pour cette énigme.'
            ], Response::HTTP_NOT_FOUND);
        }

        // return response()->json([
        //     'data' => [
        //         'game_session' => $gameSession->only(['id', 'status']),
        //         'session_steps' => $gameSession->only(['sessionSteps'])
        //     ]
        // ], Response::HTTP_OK);
        return response()->json([
            'data' => $gameSession,
        ], Response::HTTP_OK);
    }
}