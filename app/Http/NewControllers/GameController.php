<?php

namespace App\Http\NewControllers;

use App\Interfaces\GameplayServiceInterface;
use App\Models\GameSession;
use App\Models\Riddle;
use App\Interfaces\GameServiceInterface;
use App\Interfaces\GameSessionServiceInterface;
use App\Models\SessionStep;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class GameController extends Controller
{
    protected $gameService;

    public function __construct(GameSessionServiceInterface $gameService)
    {
        $this->gameService = $gameService;
    }

    /**
     * Start a new game session for a riddle and abandon other active session, or get active session.
     *
     * @param  \Illuminate\Http\Request
     * @return \Illuminate\Http\JsonResponse
     */
    public function playRiddle(Riddle $riddle, Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        // check if a game_session exists for this riddle
        $existingSession = GameSession::where('riddle_id', $riddle->id)
            ->where('user_id', $userId)
            // ->where('status', 'active')
            ->first();
        
        // if yes and it's an active game session, return it
        if ($existingSession && $existingSession->status === 'active') {
            return response()->json([
                'data' => $existingSession,
            ], Response::HTTP_OK);
        }
        
        // if not and the riddle is private, check the password
        if (!$existingSession && $riddle->is_private) {
            $validatedData = $request->validate([
                'password' => 'required|string|max:255'
            ]);

            if ($riddle->password !== $validatedData['password']) {
                return response()->json([
                    'message' => 'Le mot de passe est incorrect.'
                ], Response::HTTP_FORBIDDEN);
            }
        }

        DB::beginTransaction();
        try {
            // delete the existing game_session and session_steps
            if ($existingSession) {
                $existingSession->sessionSteps()->delete();
                $existingSession->delete();
            }

            // abandon any existing active game_sessions and  the active session_step
            $activeSessions = GameSession::where('user_id', $userId)
                ->where('status', 'active')
                ->get();

            foreach ($activeSessions as $session) {
                $session->status = 'abandoned';
                $session->save();

                SessionStep::where('game_session_id', $session->id)
                    ->where('status', 'active')
                    ->update(['status' => 'abandoned', 'end_time' => now()]);
                }

            // create the new game_session and the first session_step
            $gameSession = GameSession::create([
                'riddle_id' => $riddle->id,
                'user_id' => $userId,
                'status' => 'active',
                'score' => 0,
            ]);
                
            $firstStep = $riddle->steps()->orderBy('order_number')->first();

            if (!$firstStep) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Cette énigme ne contient aucune étape.'
                ], Response::HTTP_BAD_REQUEST);
            }
            
            SessionStep::create([
                'game_session_id' => $gameSession->id,
                'step_id' => $firstStep->id,
                'status' => 'active',
                'start_time' => Carbon::now(),
                'extra_hints' => 0,
            ]);

            DB::commit();

            return response()->json([
                'data' => $gameSession,
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error starting new game: ' . $e->getMessage());
            return response()->json([
                'message' => 'Erreur serveur : la création de la nouvelle partie a échouée.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    /**
     * Abandon an active game session.
     *
     * @param  \App\Models\GameSession $gameSession
     * @return \Illuminate\Http\JsonResponse
     */
    public function abandonSession(GameSession $gameSession): JsonResponse
    {
        if (Auth::id() !== $gameSession->user_id) {
            return response()->json(['message' => 'Utilisateur non autorisé.'], Response::HTTP_FORBIDDEN);
        }

        if ($gameSession->status !== 'active') {
            return response()->json(['message' => 'La partie est déjà terminée ou abandonnée.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            DB::transaction(function () use ($gameSession) {
                $gameSession->status = 'abandoned';
                $gameSession->save();
                
                $activeSessionStep = $gameSession->latestActiveSessionStep;
                if ($activeSessionStep) {
                    $activeSessionStep->status = 'abandoned';
                    $activeSessionStep->end_time = Carbon::now();
                    $activeSessionStep->save();
                }
            });
            
            // return response()->json(['message' => 'Partie abandonnée avec succès.'], Response::HTTP_OK);
            return response()->json([
                'data' => $gameSession,
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Error abandoning game: ' . $e->getMessage());
            return response()->json(['message' => 'Échec lors de l\'abandon de la partie.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    /**
     * Get the authenticated user's active game session.
     *
     * @param  \App\Models\GameSession $gameSession
     * @return \Illuminate\Http\JsonResponse
     */
    public function getActiveSession(GameSession $gameSession): JsonResponse
    {
        if (Auth::id() !== $gameSession->user_id) {
            return response()->json(['message' => 'Utilisateur non autorisé.'], Response::HTTP_FORBIDDEN);
        }

        if ($gameSession->status !== 'active') {
            return response()->json(['message' => 'La partie est déjà terminée ou abandonnée.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $sessionStep = $gameSession->latestActiveSessionStep;

        if (!$sessionStep) {
            return response()->json(['message' => 'L\'étape est déjà terminée ou abandonnée.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $stepCount = $gameSession->riddle->steps->count();

        $step = $sessionStep->step()->with(['hints' => function($query) {
            $query->orderBy('order_number', 'asc');
        }])->first();
        
        
        if (!$step) {
            return response()->json(['message' => 'Aucune étape touvée.'], Response::HTTP_NOT_FOUND);
        }

        $hints = $step->hints->map(function($hint, $index) use ($sessionStep) {
            return [
                'id' => $hint->id,
                'order_number' => $hint->order_number,
                'type' => $hint->type,
                'content' => $hint->content,
                'unlocked' => $hint->order_number === 1 || $hint->order_number <= $sessionStep->extra_hints + 1
            ];
        });

        return response()->json([
            'data' => [
                'session_step' => $sessionStep->only(['id', 'extra_hints', 'start_time']),
                'step' => $step->only(['id', 'order_number']),
                'stepsCount' => $stepCount,
                'hints' => $hints,
            ]
        ], Response::HTTP_OK);
    }


    /**
     * Get the authenticated user's completed game session.
     *
     * @param  \App\Models\GameSession $gameSession
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCompleteSession(GameSession $gameSession): JsonResponse
    {
        if (Auth::id() !== $gameSession->user_id) {
            return response()->json(['message' => 'Utilisateur non autorisé.'], Response::HTTP_FORBIDDEN);
        }

        if ($gameSession->status !== 'completed') {
            return response()->json(['message' => 'L\'énigme n\'est pas encore réussie.'], Response::HTTP_BAD_REQUEST);
        }

       $sessionSteps = $gameSession->sessionSteps()
        ->select('id', 'game_session_id', 'start_time', 'end_time', 'extra_hints')
        ->get();

        // TODO: calcul score final, get duration
        return response()->json([
            'data' => [
                'id' => $gameSession->id,
                'riddle_id' => $gameSession->riddle_id,
                'score' => $gameSession->score,
                'session_steps' => $sessionSteps
            ]
        ], Response::HTTP_OK);
    }


    /**
     * Unlock a hint for an active session step.
     *
     * @param Request $request
     * @param GameSession $gameSession
     * @return \Illuminate\Http\JsonResponse
     */
    public function unlockHint(Request $request, GameSession $gameSession)
    {
        if ($request->user()->id !== $gameSession->user_id) {
            return response()->json([
                'message' => 'Utilisateur non autorisé'
            ], Response::HTTP_FORBIDDEN);
        }
        
        $sessionStep = $gameSession->latestActiveSessionStep;
        
        if (!$sessionStep) {
            return response()->json([
                'message' => 'L\'étape est déjà terminée ou abandonnée.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        
        $validatedData = $request->validate([
            'hint_order_number' => 'required|numeric',
        ]);

        if ($validatedData['hint_order_number'] === 1 || $validatedData['hint_order_number'] <= $sessionStep->extra_hints) {
            return response()->json([
                'message' => 'Indice déjà dévérouillé.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } 

        if (($validatedData['hint_order_number'] - $sessionStep->extra_hints) > 2) {
            return response()->json([
                'message' => 'Veuillez dévérouiller l\'indice précédent.'
            ], Response::HTTP_FORBIDDEN);
        }
        
        $sessionStep->extra_hints += 1;
        $sessionStep->save();
        
        return response()->json([
            'data' => $gameSession,
        ], Response::HTTP_OK);
    }


    /**
     * Validate a step during an active game session.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \App\Models\GameSession $gameSession
     * @return \Illuminate\Http\JsonResponse
     */
    public function validateStep(Request $request, GameSession $gameSession): JsonResponse
    {
        if ($request->user()->id !== $gameSession->user_id) {
            return response()->json([
                'message' => 'Utilisateur non autorisé.'
            ], Response::HTTP_FORBIDDEN);
        }

        $sessionStep = $gameSession->latestActiveSessionStep;
        
        if (!$sessionStep) {
            return response()->json([
                'message' => 'La partie est déjà terminée ou abandonnée.'
            ], Response::HTTP_NOT_FOUND);
        }
        
        $validatedData = $request->validate([
            'qr_code' => 'required|string',
        ]);
        $step = $sessionStep->step;
        
        if ($step->qr_code !== $validatedData['qr_code']) {
            return response()->json([
                'message' => 'QR code non valide.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $data = DB::transaction(function () use ($gameSession, $sessionStep, $step) {
                $sessionStep->status = 'completed';
                $sessionStep->end_time = Carbon::now();
                $sessionStep->save();
                
                // $duration = $sessionStep->end_time->diffInSeconds($sessionStep->start_time);
                // $hintPenalty = $sessionStep->extra_hints * 10; // 10 points de pénalité par indice
                // $stepScore = max(0, 100 - $hintPenalty - min(50, $duration / 60)); // Max 50 points de pénalité pour le temps
                // $stepScore = calculateStepScore($sessionStep);
                // $gameSession->score += $stepScore;
                
                $nextStep = $gameSession->riddle->steps()
                    ->where('order_number', '>', $step->order_number)
                    ->orderBy('order_number')
                    ->first();
                    
                if ($nextStep) {
                    SessionStep::create([
                        'game_session_id' => $gameSession->id,
                        'step_id' => $nextStep->id,
                        'status' => 'active',
                        'start_time' => Carbon::now(),
                        'extra_hints' => 0,
                    ]);

                } else {
                    $gameSession->status = 'completed';
                }
                
                $gameSession->save();
                
                return [
                    'game_completed' => !$nextStep,
                    'game_session' => $gameSession
                ];
            });

            return response()->json([
                'data' => $data
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Error validating step: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur serveur : la validation de l\'étape a échouée.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}