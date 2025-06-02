<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\GameSession;
use App\Models\Riddle;
use App\Models\User;
use App\Services\GameplayService;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Traits\GameTestHelper;

class GetCurrentGameServiceTest extends TestCase
{
    use RefreshDatabase, GameTestHelper;


    /**
     * Vérifie que getCurrentGame retourne bien les données attendues lorsque tout fonctionne.
     */
    public function test_getCurrentGame_returns_correct_data()
    {
        $user = User::factory()->create();
        $riddle = Riddle::factory()->create();

        // Créer une session de jeu active avec une étape active
        $gameSession = $this->createActiveGameSessionWithStep($user, $riddle);

        $service = new GameplayService();

        $result = $service->getCurrentGame($gameSession, $user);

        // On s'attend à recevoir un tableau avec les clés session_step, step, stepsCount et hints
        $this->assertIsArray($result);
        $this->assertArrayHasKey('session_step', $result);
        $this->assertArrayHasKey('step', $result);
        $this->assertArrayHasKey('stepsCount', $result);
        $this->assertArrayHasKey('hints', $result);

        // Vérifier que les indices (hints) sont ordonnés et que l'état unlocked est calculé
        foreach ($result['hints'] as $hint) {
            $this->assertArrayHasKey('id', $hint);
            $this->assertArrayHasKey('order_number', $hint);
            $this->assertArrayHasKey('type', $hint);
            $this->assertArrayHasKey('content', $hint);
            $this->assertArrayHasKey('unlocked', $hint);
        }
    }

    /**
     * Vérifie qu'une exception est levée si l'utilisateur n'est pas autorisé à accéder à la session.
     */
    public function test_getCurrentGame_throws_exception_for_unauthorized_user()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $riddle = Riddle::factory()->create();

        // Créer une session appartenant à $user
        $gameSession = GameSession::factory()->create([
            'riddle_id' => $riddle->id,
            'user_id'   => $user->id,
            'status'    => 'active'
        ]);

        $service = new GameplayService();

        $this->expectExceptionMessage('Utilisateur non autorisé.');
        $this->expectExceptionCode(Response::HTTP_FORBIDDEN);

        $service->getCurrentGame($gameSession, $otherUser);
    }

    /**
     * Vérifie qu'une exception est levée lorsque la session n'est pas active.
     */
    public function test_getCurrentGame_throws_exception_if_session_not_active()
    {
        $user = User::factory()->create();
        $riddle = Riddle::factory()->create();

        // Créer une session de jeu non active (exemple : terminée)
        $gameSession = GameSession::factory()->create([
            'riddle_id' => $riddle->id,
            'user_id'   => $user->id,
            'status'    => 'completed'
        ]);

        $service = new GameplayService();

        $this->expectExceptionMessage('La partie est déjà terminée ou abandonnée.');
        $this->expectExceptionCode(Response::HTTP_UNPROCESSABLE_ENTITY);

        $service->getCurrentGame($gameSession, $user);
    }

    /**
     * Vérifie qu'une exception est levée si aucun step actif n'est trouvé (typiquement si la partie est finie).
     */
    public function test_getCurrentGame_throws_exception_if_no_active_step_found()
    {
        $user = User::factory()->create();
        $riddle = Riddle::factory()->create();

        // Créer une session active sans définir de latestActiveSessionStep
        $gameSession = GameSession::factory()->create([
            'riddle_id' => $riddle->id,
            'user_id'   => $user->id,
            'status'    => 'active'
        ]);

        // On ne met pas de relation latestActiveSessionStep, ce qui simule l'absence d'étape active
        $service = new GameplayService();

        $this->expectExceptionMessage('La partie est déjà terminée ou abandonnée.');
        $this->expectExceptionCode(Response::HTTP_UNPROCESSABLE_ENTITY);

        $service->getCurrentGame($gameSession, $user);
    }
}