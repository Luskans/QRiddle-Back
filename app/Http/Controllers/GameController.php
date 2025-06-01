<?php

namespace App\Http\Controllers;

use App\Interfaces\GameplayServiceInterface;
use App\Models\GameSession;
use App\Models\Riddle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use App\Services\GameServiceInterface;
use Carbon\Carbon;

class GameController extends Controller
{
    protected $gameplayService;

    public function __construct(GameplayServiceInterface $gameplayService)
    {
        $this->gameplayService = $gameplayService;
    }

    public function playRiddle(Riddle $riddle, Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $gameSession = $this->gameplayService->startGame($riddle, $user, $request);
            
            return response()->json([
                'data' => $gameSession,
            ], Response::HTTP_OK);

        // } catch (\Illuminate\Validation\ValidationException $e) {
        //     return response()->json([
        //         'message' => $e->getMessage()
        //     ], Response::HTTP_UNPROCESSABLE_ENTITY);

        } catch (\Exception $e) {
            Log::error('Error starting new game: ' . $e->getMessage());
            return response()->json(['message' => $e->getMessage() | 'Erreur serveur lors de la création de la nouvelle partie.'], $e->getCode() ?: Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function abandonSession(GameSession $gameSession): JsonResponse
    {
        $user = Auth::user();
        // if ($user->id !== $gameSession->user_id) {
        //     return response()->json(['message' => 'Utilisateur non autorisé.'], Response::HTTP_FORBIDDEN);
        // }

        try {
            $gameSession = $this->gameplayService->abandonGame($gameSession, $user);
            
            return response()->json([
                'data' => $gameSession,
            ], Response::HTTP_OK);

        // } catch (\Illuminate\Validation\ValidationException $e) {
        //     return response()->json([
        //         'message' => $e->getMessage()
        //     ], Response::HTTP_UNPROCESSABLE_ENTITY);

        } catch (\Exception $e) {
            Log::error('Error abandoning game: ' . $e->getMessage());
            return response()->json(['message' => $e->getMessage() | 'Erreur serveur lors de l\'abandon de la partie.'], $e->getCode() ?: Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getActiveSession(GameSession $gameSession): JsonResponse
    {
        $user = Auth::user();
        // if ($user->id !== $gameSession->user_id) {
        //     return response()->json(['message' => 'Utilisateur non autorisé.'], Response::HTTP_FORBIDDEN);
        // }

        try {
            $activeSession = $this->gameplayService->getCurrentGame($gameSession, $user);
            
            return response()->json([
                'data' => $activeSession,
            ], Response::HTTP_OK);

        // } catch (\Illuminate\Validation\ValidationException $e) {
        //     return response()->json([
        //         'message' => $e->getMessage()
        //     ], Response::HTTP_UNPROCESSABLE_ENTITY);

        } catch (\Exception $e) {
            Log::error('Error get active session: ' . $e->getMessage());
            return response()->json(['message' => $e->getMessage() | 'Erreur serveur lors de la récupération de la partie en cours.'], $e->getCode() ?: Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getCompletedSession(GameSession $gameSession): JsonResponse
    {
        $user = Auth::user();
        // if ($user->id !== $gameSession->user_id) {
        //     return response()->json(['message' => 'Utilisateur non autorisé.'], Response::HTTP_FORBIDDEN);
        // }

        try {
            $completedSession = $this->gameplayService->getCompletedGame($gameSession, $user);
            
            return response()->json([
                'data' => $completedSession,
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Error get completed session: ' . $e->getMessage());
            return response()->json(['message' => $e->getMessage() | 'Erreur serveur lors de la récupération de la partie complétée.'], $e->getCode() ?: Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function unlockHint(Request $request, GameSession $gameSession): JsonResponse
    {
        $user = $request->user();
        // if ($user->id !== $gameSession->user_id) {
        //     return response()->json(['message' => 'Utilisateur non autorisé.'], Response::HTTP_FORBIDDEN);
        // }

        $validated = $request->validate([
            'hint_order_number' => 'required|numeric',
        ]);

        try {
            $gameSession = $this->gameplayService->unlockHint($gameSession, $user, $validated['hint_order_number']);
            
            return response()->json([
                'data' => $gameSession,
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Error unlock hint: ' . $e->getMessage());
            return response()->json(['message' => $e->getMessage() | 'Erreur serveur lors du dévérouillage d\'un indice.'], $e->getCode() ?: Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function validateStep(Request $request, GameSession $gameSession): JsonResponse
    {
        $user = $request->user();
        // if ($user->id !== $gameSession->user_id) {
        //     return response()->json(['message' => 'Utilisateur non autorisé.'], Response::HTTP_FORBIDDEN);
        // }

        $validated = $request->validate([
            'qr_code' => 'required|string',
        ]);

        try {
            $data = $this->gameplayService->validateStep($gameSession, $user, $validated['qr_code']);
            
            return response()->json([
                'data' => $data,
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Error unlock hint: ' . $e->getMessage());
            return response()->json(['message' => $e->getMessage() | 'Erreur serveur lors du dévérouillage d\'un indice.'], $e->getCode() ?: Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}