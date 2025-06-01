<?php

namespace App\Http\Controllers;

use App\Http\Resources\RiddleDetailResource;
use App\Interfaces\RiddleServiceInterface;
use App\Models\Riddle;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class RiddleController extends Controller
{
    protected $riddleService;

    public function __construct(RiddleServiceInterface $riddleService)
    {
        $this->riddleService = $riddleService;
    }

    /**
     * Get the list of riddles.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        // TODO : use user's location to get a paginated list 
        try {
            $riddles = $this->riddleService->getPublishedRiddles();
            
            return response()->json([
                'items' => $riddles,
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Error fetching riddles: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur serveur lors de la récupération des énigmes.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
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

        try {
            $riddle = $this->riddleService->createRiddle($validatedData, $request->user()->id);
            
            return response()->json([
                'data' => $riddle,
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            Log::error('Error creating riddle: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur serveur lors de la création de l\'énigme.'], Response::HTTP_INTERNAL_SERVER_ERROR);
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
        try {
            $riddleDetail = $this->riddleService->getRiddleDetail($riddle);
            
            return response()->json([
                'data' => new RiddleDetailResource($riddleDetail),
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Error fetching riddle detail: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur serveur lors de la récupération des détails de l\'énigme.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
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
        if ($request->user()->id !== $riddle->creator_id) {
            return response()->json(['message' => 'Utilisateur non autorisé.'], Response::HTTP_FORBIDDEN);
        }

        $validatedData = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string|max:1000',
            'is_private' => 'sometimes|required|boolean',
            'status' => ['sometimes', 'required', 'in:draft,published,disabled'],
            'latitude' => ['sometimes', 'required', 'regex:/^[-]?(([0-8]?[0-9])\.(\d+))|(90(\.0+)?)$/'],
            'longitude' => ['sometimes', 'required', 'regex:/^[-]?((((1[0-7][0-9])|([0-9]?[0-9]))\.(\d+))|180(\.0+)?)$/'],
        ]);

        try {
            $updatedRiddle = $this->riddleService->updateRiddle($riddle, $validatedData);
            
            return response()->json([
                'data' => $updatedRiddle,
            ], Response::HTTP_OK);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);

        } catch (\Exception $e) {
            Log::error("Error updating riddle {$riddle->id}: " . $e->getMessage());
            return response()->json(['message' => 'Erreur serveur lors de la mise à jour de l\'énigme.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Delete (soft delete) a riddle.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Riddle  $riddle
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, Riddle $riddle): JsonResponse
    {
        if ($request->user()->id !== $riddle->creator_id) {
            return response()->json(['message' => 'Utilisateur non autorisé.'], Response::HTTP_FORBIDDEN);
        }

        try {
            $this->riddleService->deleteRiddle($riddle);
            return response()->json(null, Response::HTTP_NO_CONTENT);

        } catch (\Exception $e) {
            Log::error("Error deleting riddle {$riddle->id}: " . $e->getMessage());
            return response()->json(['message' => 'Erreur serveur : la suppression de l\'énigme a échouée.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get a game session with session steps for a riddle.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Riddle  $riddle
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSessionByRiddle(Request $request, Riddle $riddle): JsonResponse
    {
        try {
            $gameSession = $this->riddleService->getGameSessionForRiddle($riddle, $request->user()->id);
            
            if (!$gameSession) {
                return response()->json([
                    'message' => 'Aucune partie jouée pour cette énigme.'
                ], Response::HTTP_NOT_FOUND);
            }
            
            return response()->json([
                'data' => $gameSession,
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error("Error fetching game session for riddle {$riddle->id}: " . $e->getMessage());
            return response()->json(['message' => 'Erreur serveur lors de la récupération de la session de jeu.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}