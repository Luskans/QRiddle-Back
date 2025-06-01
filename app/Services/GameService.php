<?php

namespace App\Services;

use App\Interfaces\GameServiceInterface;
use App\Models\GameSession;
use App\Models\Riddle;
use App\Models\SessionStep;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;


class GameService implements GameServiceInterface
{
    public function getPlayedCount(int $userId): int
    {
        return GameSession::where('user_id', $userId)
            ->whereIn('status', ['completed', 'abandoned'])
            ->count();
    }

    public function getActiveSession(int $userId): ?GameSession
    {
        // return GameSession::with([
        //         'riddle:id,title',
        //         'sessionSteps:id' => function ($query) {
        //             $query->where('status', 'active')->with('step:id,order_number')->latest('start_time')->limit(1);
        //         }
        //     ])
        //     ->where('user_id', $userId)
        //     ->where('status', 'active')
        //     ->latest('updated_at')
        //     ->first();

        return GameSession::with([
                'riddle' => function ($query) {
                    $query->select('id', 'title', 'latitude', 'longitude')
                        ->withCount('steps');
                },
                // 'sessionSteps' => function ($query) {
                //     $query->select('id', 'hint_used_number', 'status', 'start_time')
                //         ->where('status', 'active')
                //         ->with(['step' => function ($query) {
                //             $query->select('id', 'order_number');
                //         }])
                //         ->latest('start_time')
                //         ->limit(1);
                // }
                'latestActiveSessionStep' => function ($query) {
                    $query->with(['step' => function ($query) {
                        $query->select('id', 'order_number');
                    }]);
                }
            ])
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->latest('updated_at')
            ->first();
    }

    /**
     * Démarre une nouvelle session de jeu pour un utilisateur et une énigme.
     * Crée la GameSession et la première SessionStep.
     * Vérifie si une session est déjà en cours pour cet utilisateur.
     *
     * @param int $userId
     * @param int $riddleId
     * @return array Contient la session et l'étape actuelle, ou une erreur.
     */
    public function startSession(int $userId, int $riddleId): array
    {
        // Vérifier si l'utilisateur a déjà une session active
        $existingActiveSession = $this->getActiveSession($userId);
        if ($existingActiveSession) {
            return [
                'error' => 'Vous avez déjà une partie en cours.',
                'status_code' => 409 // Conflict
            ];
        }

        // Récupérer l'énigme et sa première étape
        $riddle = Riddle::with(['steps' => function ($query) {
            $query->orderBy('order_number', 'asc')->limit(1); // Récupère seulement la première étape
        }])->find($riddleId);

        // Vérifier si l'énigme existe et est jouable (statut 'active')
        if (!$riddle || $riddle->status !== 'published') {
            return [
                'error' => 'Cette énigme n\'est pas disponible actuellement.',
                'status_code' => 400 // Bad Request
            ];
        }

        // Vérifier si l'énigme a au moins une étape
        $firstStep = $riddle->steps->first();
        if (!$firstStep) {
             return [
                'error' => 'Cette énigme n\'a pas encore d\'étapes définies.',
                'status_code' => 400 // Bad Request
            ];
        }

        // Utiliser une transaction pour assurer la cohérence
        return DB::transaction(function () use ($userId, $riddle, $firstStep) {
            // Créer la GameSession
            $gameSession = GameSession::create([
                'riddle_id' => $riddle->id,
                'user_id' => $userId,
                'status' => 'active', // Statut initial
                'score' => 0, // Score initial
            ]);

            // Créer la première SessionStep
            $sessionStep = SessionStep::create([
                'game_session_id' => $gameSession->id,
                'step_id' => $firstStep->id,
                'status' => 'active', // Statut initial de l'étape
                'start_time' => Carbon::now(), // Heure de début
                'extra_hints' => 0,
            ]);

            // Recharger les relations nécessaires pour la réponse
            $gameSession->load('riddle:id,title');
            $sessionStep->load('step:id,order_number,latitude,longitude'); // Charger l'étape de base

            // Retourner les données pour le frontend
            return [
                'session' => $gameSession,
                'current_step' => $sessionStep,
                // Optionnel: retourner les indices disponibles pour la première étape
                // 'available_hints' => $firstStep->hints()->where('order_number', 1)->get() ?? []
            ];
        });
    }

    /**
     * Marque une session de jeu et son étape active comme abandonnées.
     *
     * @param GameSession $gameSession
     * @return GameSession La session mise à jour.
     */
    public function abandonSession(GameSession $gameSession): GameSession
    {
        // Utiliser une transaction
        return DB::transaction(function () use ($gameSession) {
            // Mettre à jour le statut de la session
            $gameSession->status = 'abandoned';
            $gameSession->save();

            // Mettre à jour le statut de l'étape de session active (si elle existe)
            $activeSessionStep = $gameSession->sessionSteps()->where('status', 'active')->first();
            if ($activeSessionStep) {
                $activeSessionStep->status = 'abandoned';
                // Mettre end_time ? Optionnel pour l'abandon
                // $activeSessionStep->end_time = Carbon::now();
                $activeSessionStep->save();
            }

            // Recharger les relations si nécessaire avant de retourner
            $gameSession->load('riddle:id,title');

            return $gameSession;
        });
    }

     // ... autres méthodes ...
}