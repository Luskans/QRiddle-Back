<?php

namespace Tests\Traits;

use App\Models\GameSession;
use App\Models\Hint;
use App\Models\SessionStep;
use App\Models\Step;
use App\Models\User;
use App\Models\Riddle;
use Illuminate\Support\Carbon;

trait GameTestHelper
{
    /**
     * Prépare une session de jeu active associée à une étape ayant le QR code spécifié.
     *
     * @param User $user
     * @param Riddle $riddle
     * @param string $qrCode
     * @param int $order L'ordre de l'étape (par défaut 1)
     * @return GameSession
     */
    public function createGameSessionWithActiveStep(User $user, Riddle $riddle, string $qrCode, int $order = 1): GameSession
    {
        $gameSession = GameSession::factory()->create([
            'riddle_id' => $riddle->id,
            'user_id'   => $user->id,
            'status'    => 'active',
            'score'     => 0,
        ]);

        $step = Step::factory()->create([
            'riddle_id'    => $riddle->id,
            'order_number' => $order,
            'qr_code'      => $qrCode,
        ]);

        $sessionStep = SessionStep::factory()->create([
            'game_session_id' => $gameSession->id,
            'step_id'         => $step->id,
            'status'          => 'active',
            'extra_hints'     => 0,
            'start_time'      => Carbon::now()->subMinutes(5),
            'end_time'        => null,
        ]);

        $gameSession->setRelation('latestActiveSessionStep', $sessionStep);
        $sessionStep->setRelation('step', $step);

        return $gameSession;
    }

    /**
     * Crée une session de jeu complétée avec quelques SessionStep.
     *
     * @param User $user
     * @param Riddle $riddle
     * @return GameSession
     */
    public function createCompletedGameSession(User $user, Riddle $riddle): GameSession
    {
        $gameSession = GameSession::factory()->create([
            'riddle_id' => $riddle->id,
            'user_id'   => $user->id,
            'status'    => 'completed',
            'score'     => 150,
        ]);

        $sessionSteps = collect([
            SessionStep::factory()->create([
                'game_session_id' => $gameSession->id,
                'extra_hints'     => 1,
                'start_time'      => now()->subMinutes(10),
                'end_time'        => now()->subMinutes(5),
            ]),
            SessionStep::factory()->create([
                'game_session_id' => $gameSession->id,
                'extra_hints'     => 0,
                'start_time'      => now()->subMinutes(4),
                'end_time'        => now()->subMinutes(2),
            ])
        ]);

        $gameSession->setRelation('sessionSteps', $sessionSteps);

        return $gameSession;
    }

    /**
     * Prépare une session de jeu active avec une étape active contenant des indices.
     *
     * @return GameSession
     */
    protected function createActiveGameSessionWithStep(User $user, Riddle $riddle)
    {
        // Création d'un GameSession actif (sans utiliser une factory pour garder le contrôle)
        $gameSession = GameSession::factory()->create([
            'riddle_id' => $riddle->id,
            'user_id'   => $user->id,
            'status'    => 'active',
            'score'     => 0,
        ]);

        // Création d'une étape pour le riddle
        $step = Step::factory()->create([
            'riddle_id'    => $riddle->id,
            'order_number' => 1,
            'qr_code'      => 'QR123456'
        ]);

        // Création d'un SessionStep associé à la gameSession, simulant une étape active
        $sessionStep = SessionStep::factory()->create([
            'game_session_id' => $gameSession->id,
            'step_id'         => $step->id,
            'status'          => 'active',
            'extra_hints'     => 1,
            'start_time'      => Carbon::now()->subMinutes(5),
            'end_time'        => null, // encore actif
        ]);

        // Afin que getCurrentGame puisse récupérer le step avec ses indices,
        // nous forçons son chargement via la relation avec hints.
        $hint1 = Hint::factory()->make([
            'step_id' => $step->id,
            'order_number' => 1,
            'type' => 'text',
            'content' => 'Indice 1',
        ]);

        $hint2 = Hint::factory()->make([
            'step_id' => $step->id,
            'order_number' => 2,
            'type' => 'text',
            'content' => 'Indice 2',
        ]);

        $step->setRelation('hints', collect([$hint1, $hint2]));
        
        // Liez le SessionStep fraîchement créé
        $gameSession->setRelation('latestActiveSessionStep', $sessionStep);
        // On charge la relation step dans SessionStep afin que getCurrentGame puisse l'utiliser
        $sessionStep->setRelation('step', $step);

        return $gameSession;
    }

    /**
     * Prépare une énigme avec au moins une étape en base.
     *
     * @return Riddle
     */
    protected function createRiddleWithSteps()
    {
        // Création d'une énigme publique avec un créateur
        $riddle = Riddle::factory()->create([
            'is_private' => false,
            'password'   => null,
        ]);

        // Crée une étape pour l'énigme
        Step::factory()->create([
            'riddle_id'    => $riddle->id,
            'order_number' => 1,
            // Vous pouvez définir d'autres valeurs (qr_code, latitude, longitude) si besoin
        ]);

        return $riddle;
    }
}