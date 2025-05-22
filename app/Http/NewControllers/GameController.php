<?php

namespace App\Http\NewControllers;

use App\Models\GameSession;
use App\Models\Riddle;
use App\Interfaces\GameServiceInterface;
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

    public function __construct(GameServiceInterface $gameService)
    {
        $this->gameService = $gameService;
    }

    /**
     * Start a new game session for a riddle and abandon other active session.
     *
     * @param  \Illuminate\Http\Request
     * @return \Illuminate\Http\JsonResponse
     */
    public function playRiddle(Riddle $riddle, Request $request): JsonResponse
    {
        $userId = Auth::id();

        $validatedData = $request->validate([
            'password' => 'nullable|string|max:255'
        ]);

        try {            
            if (!$riddle || $riddle->status !== 'active') {
                return response()->json(['message' => 'Cette énigme n\'est pas disponible actuellement.'], Response::HTTP_BAD_REQUEST);
            }

            $firstStep= $riddle->steps->first();
            if (!$firstStep) {
                return response()->json(['message' => 'Cette énigme n\'a pas encore d\'étape.'], Response::HTTP_BAD_REQUEST);
            }

            $firstHint = $firstStep->hints->first();
            if (!$firstHint) {
                return response()->json(['message' => 'Cette énigme n\'a pas encore d\'indice.'], Response::HTTP_BAD_REQUEST);
            }

            if ($riddle->is_private && $riddle->password !== $validatedData['password']) {
                return response()->json(['message' => 'Le mot de passe est incorrect.'], Response::HTTP_FORBIDDEN);
            }
            
            DB::transaction(function () use ($userId) {
                $activeSessions = GameSession::where('player_id', $userId)
                    ->where('status', 'active')
                    ->get();

                foreach ($activeSessions as $session) {
                    $session->status = 'abandoned';
                    $session->save();
                }
            });

            $gameSession = DB::transaction(function () use ($userId, $riddle, $firstStep) {
                $gameSession = GameSession::create([
                    'riddle_id' => $riddle->id,
                    'player_id' => $userId,
                    'status' => 'active',
                    'score' => 0,
                ]);

                SessionStep::create([
                    'game_session_id' => $gameSession->id,
                    'step_id' => $firstStep->id,
                    'status' => 'active',
                    'extra_hints' => 0,
                ]);

                return $gameSession;
            });

            // return response()->json($gameSession, Response::HTTP_CREATED);
            // return response()->json([
            //     'success' => true,
            //     'message' => 'Nouvelle partie créée avec succès',
            //     'data' => [
            //         'game_session' => $gameSession,
            //         'first_step' => [
            //             'id' => $firstStep->id,
            //             'order_number' => $firstStep->order_number
            //         ]
            //     ]
            // ], Response::HTTP_CREATED);
            return response()->json([
                'data' => $gameSession,
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            Log::error('Error starting new game: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur serveur : la création de la nouvelle partie a échouée.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function playRiddle(Request $request, Riddle $riddle): JsonResponse
    {
        $userId = Auth::id();
        
        // 1. Vérifier si l'énigme est disponible
        if ($riddle->status !== 'active') {
            return response()->json([
                'message' => 'Cette énigme n\'est pas disponible actuellement.'
            ], Response::HTTP_BAD_REQUEST);
        }
        
        // 2. Vérifier si l'énigme a des étapes et des indices
        if ($riddle->steps->isEmpty() || $riddle->steps->first()->hints->isEmpty()) {
            return response()->json([
                'message' => 'Cette énigme n\'est pas complète (étapes ou indices manquants).'
            ], Response::HTTP_BAD_REQUEST);
        }
        
        // 3. Vérifier si une partie active existe déjà pour cette énigme
        $existingSession = GameSession::where('riddle_id', $riddle->id)
            ->where('player_id', $userId)
            ->where('status', 'active')
            ->first();
        
        if ($existingSession) {
            // 3a. Une partie active existe, la retourner
            return response()->json([
                'message' => 'Partie en cours reprise',
                'session' => $existingSession,
                'action' => 'resumed'
            ], Response::HTTP_OK);
        }
        
        // 4. Vérifier le mot de passe si l'énigme est privée
        if ($riddle->is_private) {
            $validatedData = $request->validate([
                'password' => 'required|string'
            ]);
            
            if (!Hash::check($validatedData['password'], $riddle->password)) {
                return response()->json([
                    'message' => 'Le mot de passe est incorrect.'
                ], Response::HTTP_FORBIDDEN);
            }
        }
        
        // 5. Vérifier s'il existe une partie terminée ou abandonnée
        $oldSession = GameSession::where('riddle_id', $riddle->id)
            ->where('player_id', $userId)
            ->whereIn('status', ['completed', 'abandoned'])
            ->first();
        
        // 6. Créer une nouvelle partie
        try {
            DB::beginTransaction();
            
            // 6a. Si une ancienne session existe, la supprimer ou la mettre à jour
            if ($oldSession) {
                // Option 1: Supprimer l'ancienne session et ses étapes
                $oldSession->sessionSteps()->delete();
                $oldSession->delete();
                
                // Option 2 (alternative): Mettre à jour l'ancienne session
                // $oldSession->status = 'active';
                // $oldSession->score = 0;
                // $oldSession->save();
                // $oldSession->sessionSteps()->delete();
            }
            
            // 6b. Créer une nouvelle session
            $gameSession = GameSession::create([
                'riddle_id' => $riddle->id,
                'player_id' => $userId,
                'status' => 'active',
                'score' => 0,
            ]);
            
            // 6c. Créer la première étape de session
            $firstStep = $riddle->steps()->orderBy('order_number')->first();
            
            SessionStep::create([
                'game_session_id' => $gameSession->id,
                'step_id' => $firstStep->id,
                'status' => 'active',
                'start_time' => Carbon::now(),
                'extra_hints' => 0,
            ]);
            
            DB::commit();
            
            return response()->json([
                'message' => 'Nouvelle partie démarrée',
                'session' => $gameSession,
                'action' => 'created'
            ], Response::HTTP_CREATED);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating game session: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Échec lors de la création de la partie.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get the authenticated user's active game session.
     *
     * @param  \App\Models\GameSession
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSession(GameSession $gameSession): JsonResponse
    {
        if (Auth::id() !== $gameSession->player_id) {
            return response()->json(['message' => 'Utilisateur non autorisé.'], Response::HTTP_FORBIDDEN);
        }

        if ($gameSession->status !== 'active') {
            return response()->json(['message' => 'La partie est déjà terminée ou abandonnée.'], Response::HTTP_BAD_REQUEST);
        }

        $sessionStep = $gameSession->latestActiveSessionStep;

        if (!$sessionStep) {
            return response()->json(['message' => 'L\'étape est déjà terminée ou abandonnée.'], Response::HTTP_BAD_REQUEST);
        }

        $stepCount = $gameSession->riddle()->steps()->count();

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
                'step_count' => $stepCount,
                'hints' => $hints,
            ]
        ], Response::HTTP_OK);
    }

    /**
     * Unlock a hint for active session step.
     *
     * @param Request $request
     * @param GameSession $gameSession
     * @return \Illuminate\Http\JsonResponse
     */
    public function unlockHint(Request $request, GameSession $gameSession)
    {
        if (Auth::id() !== $gameSession->player_id) {
            return response()->json(['message' => 'Utilisateur non autorisé'], Response::HTTP_FORBIDDEN);
        }
        
        $sessionStep = $gameSession->latestActiveSessionStep;
        
        if (!$sessionStep) {
            return response()->json(['message' => 'L\'étape est déjà terminée ou abandonnée.'], Response::HTTP_BAD_REQUEST);
        }
        
        $validatedData = $request->validate([
            'hint_order_number' => 'required|numeric',
        ]);

        if ($validatedData['hint_order_number'] === 1 || $validatedData['hint_order_number'] <= $sessionStep->extra_hints) {
            return response()->json(['message' => 'Indice déjà dévérouillé.'], Response::HTTP_BAD_REQUEST);
        } 

        if (($validatedData['hint_order_number'] - $sessionStep->extra_hints) > 1) {
            return response()->json(['message' => 'Veuillez dévérouiller l\'indice précédent.'], Response::HTTP_BAD_REQUEST);
        }
        
        $sessionStep->extra_hints += 1;
        $sessionStep->save();
        
        return $this->activeSession($gameSession);
    }

    /**
     * Abandon an active game session.
     *
     * @param  \App\Models\GameSession
     * @return \Illuminate\Http\JsonResponse
     */
    public function abandonSession(GameSession $gameSession): JsonResponse
    {
        if (Auth::id() !== $gameSession->player_id) {
            return response()->json(['message' => 'Utilisateur non autorisé.'], Response::HTTP_FORBIDDEN);
        }

        if ($gameSession->status !== 'active') {
            return response()->json(['message' => 'La partie est déjà terminée ou abandonnée.'], Response::HTTP_BAD_REQUEST);
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
     * Validate a step in the active game session.
     *
     * @param  \Illuminate\Http\Request
     * @param  \App\Models\GameSession
     * @return \Illuminate\Http\JsonResponse
     */
    public function validateStep(Request $request, GameSession $gameSession): JsonResponse
    {
        if (Auth::id() !== $gameSession->player_id) {
            return response()->json(['message' => 'Utilisateur non autorisé.'], Response::HTTP_FORBIDDEN);
        }

        if ($gameSession->status !== 'active') {
            return response()->json(['message' => 'La partie est déjà terminée ou abandonnée.'], Response::HTTP_BAD_REQUEST);
        }

        $sessionStep = $gameSession->latestActiveSessionStep;
        
        if (!$sessionStep) {
            return response()->json(['message' => 'Aucune étape active trouvée.'], Response::HTTP_NOT_FOUND);
        }
        
        $validatedData = $request->validate([
            'qr_code' => 'required|string',
        ]);
        $step = $sessionStep->step;
        
        if ($step->qr_code !== $validatedData['qr_code']) {
            return response()->json(['message' => 'QR code invalide.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            return DB::transaction(function () use ($gameSession, $sessionStep, $step) {
                $sessionStep->status = 'completed';
                $sessionStep->end_time = Carbon::now();
                $sessionStep->save();
                
                // Calculer le score pour cette étape (à adapter selon votre logique)
                $duration = $sessionStep->end_time->diffInSeconds($sessionStep->start_time);
                $hintPenalty = $sessionStep->extra_hints * 10; // 10 points de pénalité par indice
                $stepScore = max(0, 100 - $hintPenalty - min(50, $duration / 60)); // Max 50 points de pénalité pour le temps
                
                $gameSession->score += $stepScore;
                
                // Vérifier s'il y a une étape suivante
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
                
                if (!$nextStep) {
                    return response()->json([
                        'data' => [
                            'game_completed' => true,
                            'game_session' => $gameSession
                        ]
                    ], Response::HTTP_OK);
                }
                
                return $this->activeSession($gameSession);
            });
        } catch (\Exception $e) {
            Log::error('Error validating step: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur serveur : la validation de l\'étape a échouée.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}