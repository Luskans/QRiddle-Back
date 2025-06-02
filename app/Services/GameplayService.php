<?php

namespace App\Services;

use App\Interfaces\GameplayServiceInterface;
use App\Interfaces\ScoreServiceInterface;
use App\Models\Riddle;
use App\Models\GameSession;
use App\Models\Review;
use App\Models\SessionStep;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class GameplayService implements GameplayServiceInterface
{
    public function startGame(Riddle $riddle, User $user, Request $request): GameSession
    {
        $existingSession = GameSession::where('riddle_id', $riddle->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existingSession && $existingSession->status === 'active') {
            return $existingSession;
        }

        if (!$existingSession && $riddle->is_private) {
            $validated = $request->validate([
                'password' => 'required|string|max:255',
            ]);

            if ($riddle->password !== $validated['password']) {
                throw new \Exception('Mot de passe incorrect.', Response::HTTP_FORBIDDEN);
            }
        }

        return DB::transaction(function () use ($riddle, $user, $existingSession) {
            if ($existingSession) {
                $existingSession->sessionSteps()->delete();
                $existingSession->delete();
            }

            GameSession::where('user_id', $user->id)
                ->where('status', 'active')
                ->each(function ($session) {
                    $session->update(['status' => 'abandoned']);
                    $session->sessionSteps()
                        ->where('status', 'active')
                        ->update(['status' => 'abandoned', 'end_time' => now()]);
                });

            $gameSession = GameSession::create([
                'riddle_id' => $riddle->id,
                'user_id' => $user->id,
                'status' => 'active',
                'score' => 0,
            ]);

            $firstStep = $riddle->steps()->orderBy('order_number')->first();

            if (!$firstStep) {
                throw new \Exception('Cette énigme ne contient aucune étape.', Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            SessionStep::create([
                'game_session_id' => $gameSession->id,
                'step_id' => $firstStep->id,
                'status' => 'active',
                'start_time' => now(),
                'extra_hints' => 0,
            ]);

            return $gameSession;
        });
    }

    public function abandonGame(GameSession $session, User $user): GameSession
    {
        if ($session->user_id !== $user->id) {
            throw new \Exception('Utilisateur non autorisé.', Response::HTTP_FORBIDDEN);
        }

        if ($session->status !== 'active') {
            throw new \Exception('La partie est déjà terminée ou abandonnée.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return DB::transaction(function () use ($session) {
            $session->update(['status' => 'abandoned']);

            $step = $session->latestActiveSessionStep;
            if ($step) {
                $step->update(['status' => 'abandoned', 'end_time' => now()]);
            }

            return $session;
        });
    }

    public function getCurrentGame(GameSession $session, User $user): array
    {
        if ($session->user_id !== $user->id) {
            throw new \Exception('Utilisateur non autorisé.', Response::HTTP_FORBIDDEN);
        }

        if ($session->status !== 'active') {
            throw new \Exception('La partie est déjà terminée ou abandonnée.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $step = $session->latestActiveSessionStep?->step()->with('hints')->first();

        if (!$step) {
            throw new \Exception('La partie est déjà terminée ou abandonnée.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $stepCount = $session->riddle->steps->count();

        $hints = $step->hints->sortBy('order_number')->map(function ($hint) use ($session) {
            $extra = $session->latestActiveSessionStep->extra_hints;
            return [
                'id' => $hint->id,
                'order_number' => $hint->order_number,
                'type' => $hint->type,
                'content' => $hint->content,
                'unlocked' => $hint->order_number === 1 || $hint->order_number <= $extra + 1
            ];
        });

        return [
            'session_step' => $session->latestActiveSessionStep->only(['id', 'extra_hints', 'start_time']),
            'step' => $step->only(['id', 'order_number']),
            'stepsCount' => $stepCount,
            'hints' => $hints->values()
        ];
    }

    public function getCompletedGame(GameSession $session, User $user): array
    {
        if ($session->user_id !== $user->id) {
            throw new \Exception('Utilisateur non autorisé.', Response::HTTP_FORBIDDEN);
        }

        if ($session->status !== 'completed') {
            throw new \Exception('L\'énigme n\'est pas encore réussie.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $hasReviewed = Review::where('user_id', $user->id)
            ->where('riddle_id', $session->riddle_id)
            ->exists();

        return [
            'id' => $session->id,
            'riddle_id' => $session->riddle_id,
            'score' => $session->score,
            'duration' => $session->getTotalDuration(),
            'has_reviewed' => $hasReviewed,
            'session_steps' => $session->sessionSteps()->select('id', 'game_session_id', 'start_time', 'end_time', 'extra_hints')->get()
        ];
    }

    public function unlockHint(GameSession $session, User $user, int $hintOrder): GameSession
    {
        if ($session->user_id !== $user->id) {
            throw new \Exception('Utilisateur non autorisé.', Response::HTTP_FORBIDDEN);
        }

        $step = $session->latestActiveSessionStep;

        if (!$step) {
            throw new \Exception('L\'étape est déjà terminée ou abandonnée.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($hintOrder === 1 || $hintOrder <= $step->extra_hints) {
            throw new \Exception('Indice déjà déverrouillé.', Response::HTTP_BAD_REQUEST);

        }

        if (($hintOrder - $step->extra_hints) > 2) {
            throw new \Exception('Veuillez débloquer l\'indice précédent.', Response::HTTP_FORBIDDEN);
        }

        $step->increment('extra_hints');
        return $session->fresh();
    }

    public function validateStep(GameSession $session, User $user, string $qrCode): array
    {
        if ($session->user_id !== $user->id) {
            throw new \Exception('Utilisateur non autorisé.', Response::HTTP_FORBIDDEN);
        }

        $step = $session->latestActiveSessionStep?->step;

        if (!$step || $step->qr_code !== $qrCode) {
            throw new \Exception('QR code non valide.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return DB::transaction(function () use ($session, $step) {
            $sessionStep = $session->latestActiveSessionStep;

            $sessionStep->update([
                'status' => 'completed',
                'end_time' => now()
            ]);

            $nextStep = $session->riddle->steps()
                ->where('order_number', '>', $step->order_number)
                ->orderBy('order_number')
                ->first();

            if ($nextStep) {
                SessionStep::create([
                    'game_session_id' => $session->id,
                    'step_id' => $nextStep->id,
                    'status' => 'active',
                    'start_time' => now(),
                    'extra_hints' => 0
                ]);

            } else {
                // $session->update(['status' => 'completed']);

                 // C'est la dernière étape, l'énigme est complétée
                    // Calculer le score final
                $scoreService = app(ScoreServiceInterface::class);
                $finalScore = $scoreService->calculateFinalScore($session);
                
                // Mettre à jour la session avec le score final
                $session->update([
                    'status' => 'completed',
                    'score' => $finalScore
                ]);
                
                // Mettre à jour les scores globaux
                $scoreService->updateGlobalScores($session->user_id, $finalScore);
            }

            return [
                'game_completed' => !$nextStep,
                'game_session' => $session->fresh()
            ];
        });
    }
}
