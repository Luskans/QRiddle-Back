<?php

namespace App\Http\Controllers;

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
     * Démarre une nouvelle partie pour une énigme donnée.
     *
     * @param  \Illuminate\Http\Request
     * @return \Illuminate\Http\JsonResponse
     */
    public function newGame(Request $request): JsonResponse
    {
        $userId = Auth::id();

        $validatedData = $request->validate([
            'riddle_id' => 'required|integer|exists:riddles,id',
            'password' => 'nullable|string|max:255'
        ]);

        try {
            $riddle = Riddle::find($validatedData['riddle_id']);
            
            // Vérifie si l'énigme existe et active
            if (!$riddle || $riddle->status !== 'active') {
                return response()->json(['message' => 'Cette énigme n\'est pas disponible actuellement.'], Response::HTTP_BAD_REQUEST);
                return response()->json([
                    'success' => false,
                    'message' => 'Message d\'erreur explicite',
                    'errors' => [
                        // Détails spécifiques sur les erreurs (facultatif)
                    ],
                    'data' => null // ou [] selon votre préférence
                ], $httpStatusCode);
            }

            // Vérifie si l'énigme a au moins une étape et un indice
            $firstStep= $riddle->steps->first();
            if (!$firstStep) {
                return response()->json(['message' => 'Cette énigme n\'a pas encore d\'étape.'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            // Vérifie si l'énigme a au moins un indice
            $firstHint = $firstStep->hints->first();
            if (!$firstHint) {
                return response()->json(['message' => 'Cette énigme n\'a pas encore d\'indice.'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            // Vérifie si le mot de passe est correct si l'énigme est privée
            if ($riddle->is_private && $riddle->password !== $validatedData['password']) {
                return response()->json(['message' => 'Le mot de passe est incorrect.'], Response::HTTP_FORBIDDEN);
            }
            
            // Abandonne les GameSessions en cours si il y en a
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
                // Créer la GameSession
                $gameSession = GameSession::create([
                    'riddle_id' => $riddle->id,
                    'player_id' => $userId,
                    'status' => 'active',
                    'score' => 0,
                ]);

                // Créer la première SessionStep
                $sessionStep = SessionStep::create([
                    'game_session_id' => $gameSession->id,
                    'step_id' => $firstStep->id,
                    'status' => 'active',
                    'start_time' => Carbon::now(),
                    'hint_used_number' => 0,
                ]);

                return $gameSession;
            });

            return response()->json($gameSession, Response::HTTP_CREATED);
            return response()->json([
                'success' => true,
                'message' => 'Nouvelle partie créée avec succès',
                'data' => [
                    'game_session' => $gameSession,
                    'first_step' => [
                        'id' => $firstStep->id,
                        'order_number' => $firstStep->order_number
                    ]
                ]
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            Log::error('Error creating step: ' . $e->getMessage());
            return response()->json(['message' => 'Échec lors de la création de la partie.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Affiche une partie en cours.
     *
     * @param  \App\Models\GameSession
     * @return \Illuminate\Http\JsonResponse
     */
    public function getActiveGame(GameSession $gameSession): JsonResponse
    {
        if (Auth::id() !== $gameSession->player_id) {
            return response()->json(['message' => 'Utilisateur non autorisé.'], Response::HTTP_FORBIDDEN);
        }

        if ($gameSession->status !== 'active') {
            return response()->json(['message' => 'La partie est déjà terminée ou abandonnée.'], Response::HTTP_BAD_REQUEST);
        }

        $sessionStep = $gameSession->latestActiveSessionStep;

        if (!$sessionStep) {
            return response()->json(['message' => 'L\'étape est déjà terminée ou abandonnée.'], Response::HTTP_NOT_FOUND);
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
                'unlocked' => $hint->order_number === 1 || $hint->order_number <= $sessionStep->hint_used_number + 1
            ];
        });

        return response()->json([
            'session_step' => $sessionStep->only(['id', 'hint_used_number', 'start_time']),
            'step' => $step->only(['id', 'order_number']),
            'step_count' => $stepCount,
            'hints' => $hints,
        ], Response::HTTP_OK);
    }


    /**
     * Affiche une partie complétée, abandonnée ou en cours pour une énigme donnée.
     *
     * @param  \App\Models\Riddle
     * @return \Illuminate\Http\JsonResponse
     */
    public function getGameSessionByRiddle(Riddle $riddle): JsonResponse
    {
        $userId = Auth::id();

        $gameSession = GameSession::select('id','status', 'riddle_id', 'player_id')
            ->where('riddle_id', $riddle->id)
            ->where('player_id', $userId)
            ->with('sessionSteps:id,game_session_id,status,start_time,end_time')
            ->first();

        if (!$gameSession) {
            return response()->json([
                'message' => 'Aucune partie jouée pour cette énigme.'
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'session' => $gameSession->only(['id', 'status']),
            'session_steps' => $gameSession->only(['sessionSteps'])
        ], Response::HTTP_OK);
    }

    /**
     * Dévérouille un indice pour l'étape en cours.
     *
     * @param Request $request
     * @param GameSession $gameSession
     * @return \Illuminate\Http\JsonResponse
     */
    public function unlockHint(Request $request, GameSession $gameSession)
    {
        // Vérifier que l'utilisateur est bien le joueur de cette session
        if (Auth::id() !== $gameSession->player_id) {
            return response()->json(['message' => 'Utilisateur non autorisé'], Response::HTTP_FORBIDDEN);
        }
        
        // Récupérer la session_step active
        $sessionStep = $gameSession->latestActiveSessionStep;
        
        if (!$sessionStep) {
            return response()->json(['message' => 'L\'étape est déjà terminée ou abandonnée.'], Response::HTTP_NOT_FOUND);
        }
        
        $validatedData = $request->validate([
            'hint_order_number' => 'required|numeric',
        ]);

        if ($validatedData['hint_order_number'] === 1 || $validatedData['hint_order_number'] <= $sessionStep->hint_used_number) {
            return response()->json(['message' => 'Indice déjà dévérouillé.'], Response::HTTP_BAD_REQUEST);
        } 

        if ($sessionStep->hint_used_number - $validatedData['hint_order_number'] > 1) {
            return response()->json(['message' => 'Veuillez dévérouiller l\'indice précédent.'], Response::HTTP_BAD_REQUEST);
        }
        
        // Incrémenter le nombre d'indices utilisés
        $sessionStep->hint_used_number += 1;
        $sessionStep->save();
        
        // Retourner les données mises à jour
        return $this->getActiveGame($gameSession);
    }

    /**
     * Abandonne une partie en cours.
     *
     * @param  \App\Models\GameSession $gameSession
     * @return \Illuminate\Http\JsonResponse
     */
    public function abandonGame(GameSession $gameSession): JsonResponse
    {
        if (Auth::id() !== $gameSession->player_id) {
            return response()->json(['message' => 'Utilisateur non autorisé.'], Response::HTTP_FORBIDDEN);
        }

        if ($gameSession->status !== 'active') {
            return response()->json(['message' => 'La partie est déjà terminée ou abandonnée.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            DB::transaction(function () use ($gameSession) {
                // Mettre à jour le statut de la session
                $gameSession->status = 'abandoned';
                $gameSession->save();
                
                // Mettre à jour le statut de la session step active
                $activeSessionStep = $gameSession->latestActiveSessionStep;
                if ($activeSessionStep) {
                    $activeSessionStep->status = 'abandoned';
                    $activeSessionStep->end_time = Carbon::now();
                    $activeSessionStep->save();
                }
            });
            
            return response()->json(['message' => 'Partie abandonnée avec succès.'], Response::HTTP_OK);
        } catch (\Exception $e) {
            Log::error('Error abandoning game: ' . $e->getMessage());
            return response()->json(['message' => 'Échec lors de l\'abandon de la partie.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Valide une étape de la partie.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \App\Models\GameSession $gameSession
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

        $validatedData = $request->validate([
            'qr_code' => 'required|string',
        ]);

        $sessionStep = $gameSession->latestActiveSessionStep;
        
        if (!$sessionStep) {
            return response()->json(['message' => 'Aucune étape active trouvée.'], Response::HTTP_NOT_FOUND);
        }

        $step = $sessionStep->step;
        
        // Vérifier si le QR code correspond
        if ($step->qr_code !== $validatedData['qr_code']) {
            return response()->json(['message' => 'QR code invalide.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            return DB::transaction(function () use ($gameSession, $sessionStep, $step) {
                // Marquer l'étape actuelle comme terminée
                $sessionStep->status = 'completed';
                $sessionStep->end_time = Carbon::now();
                $sessionStep->save();
                
                // Calculer le score pour cette étape (à adapter selon votre logique)
                $duration = $sessionStep->end_time->diffInSeconds($sessionStep->start_time);
                $hintPenalty = $sessionStep->hint_used_number * 10; // 10 points de pénalité par indice
                $stepScore = max(0, 100 - $hintPenalty - min(50, $duration / 60)); // Max 50 points de pénalité pour le temps
                
                // Mettre à jour le score de la session
                $gameSession->score += $stepScore;
                
                // Vérifier s'il y a une étape suivante
                $nextStep = $gameSession->riddle->steps()
                    ->where('order_number', '>', $step->order_number)
                    ->orderBy('order_number')
                    ->first();
                    
                if ($nextStep) {
                    // Créer une nouvelle session step pour l'étape suivante
                    SessionStep::create([
                        'game_session_id' => $gameSession->id,
                        'step_id' => $nextStep->id,
                        'status' => 'active',
                        'start_time' => Carbon::now(),
                        'hint_used_number' => 0,
                    ]);
                } else {
                    // C'était la dernière étape, marquer la partie comme terminée
                    $gameSession->status = 'completed';
                }
                
                $gameSession->save();
                
                // Si c'était la dernière étape, retourner un message spécial
                if (!$nextStep) {
                    return response()->json([
                        'message' => 'Félicitations! Vous avez terminé toutes les étapes.',
                        'game_completed' => true,
                        'score' => $gameSession->score
                    ], Response::HTTP_OK);
                }
                
                // Sinon, retourner les détails de la nouvelle étape
                return $this->getActiveGame($gameSession);
            });
        } catch (\Exception $e) {
            Log::error('Error validating step: ' . $e->getMessage());
            return response()->json(['message' => 'Échec lors de la validation de l\'étape.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}