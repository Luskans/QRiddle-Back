<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Riddle;
use App\Models\Step;
use App\Services\GameplayService;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Traits\GameTestHelper;

class ValidateStepServiceTest extends TestCase
{
    use RefreshDatabase, GameTestHelper;


    /**
     * Test : L'utilisateur n'est pas autorisé à valider l'étape.
     */
    public function test_validateStep_unauthorized_user()
    {
        $user = User::factory()->create(['id' => 1]);
        $otherUser = User::factory()->create(['id' => 2]);
        $riddle = Riddle::factory()->create();
        $gameSession = $this->createGameSessionWithActiveStep($user, $riddle, 'QRVALIDE');

        $service = new GameplayService();

        $this->expectExceptionMessage('Utilisateur non autorisé.');
        $this->expectExceptionCode(Response::HTTP_FORBIDDEN);

        $service->validateStep($gameSession, $otherUser, 'QRVALIDE');
    }

    /**
     * Test : Le QR code fourni est incorrect.
     */
    public function test_validateStep_invalid_qrCode()
    {
        $user = User::factory()->create();
        $riddle = Riddle::factory()->create();
        $gameSession = $this->createGameSessionWithActiveStep($user, $riddle, 'QRVALIDE');

        $service = new GameplayService();

        $this->expectExceptionMessage('QR code non valide.');
        $this->expectExceptionCode(Response::HTTP_UNPROCESSABLE_ENTITY);

        $service->validateStep($gameSession, $user, 'QRMauvais');
    }

    /**
     * Test : La validation d'une étape intermédiaire crée un nouveau SessionStep pour l'étape suivante,
     * et retourne game_completed = false.
     */
    public function test_validateStep_with_next_step()
    {
        $user = User::factory()->create();
        $riddle = Riddle::factory()->create();

        // Créer la première étape active avec QR code 'QR1'
        $gameSession = $this->createGameSessionWithActiveStep($user, $riddle, 'QR1', 1);

        // Créer une deuxième étape qui sera la prochaine étape (order_number = 2)
        $nextStep = Step::factory()->create([
            'riddle_id'    => $riddle->id,
            'order_number' => 2,
            'qr_code'      => 'QR2',
        ]);

        $service = new GameplayService();
        $result = $service->validateStep($gameSession, $user, 'QR1');

        // On s'attend à un tableau avec 'game_completed' à false et la session mise à jour
        $this->assertIsArray($result);
        $this->assertFalse($result['game_completed']);
        $this->assertArrayHasKey('game_session', $result);

        // Vérifier en base que l'ancien SessionStep est bien complété
        $this->assertDatabaseHas('session_steps', [
            'game_session_id' => $gameSession->id,
            'step_id'         => $gameSession->latestActiveSessionStep->step_id,
            'status'          => 'completed',
        ]);

        // Vérifier en base que le nouveau SessionStep pour la prochaine étape est actif
        $this->assertDatabaseHas('session_steps', [
            'game_session_id' => $gameSession->id,
            'step_id'         => $nextStep->id,
            'status'          => 'active',
        ]);

        // Vérifier que le SessionStep de l'étape validée a été mis à jour en "completed"
        $freshSession = $result['game_session'];
        // Chercher un nouveau SessionStep "active" correspondant à la deuxième étape
        $newSessionStep = $freshSession->sessionSteps()
            ->where('status', 'active')
            ->where('step_id', $nextStep->id)
            ->first();
        $this->assertNotNull($newSessionStep);
    }

    /**
     * Test : La validation de la dernière étape (aucun nextStep) complète la partie.
     */
    public function test_validateStep_last_step_completes_game()
    {
        $user = User::factory()->create();
        $riddle = Riddle::factory()->create();

        // Création d'une session pour une unique étape (aucune étape suivante)
        $gameSession = $this->createGameSessionWithActiveStep($user, $riddle, 'QRLAST', 1);

        // Assurer qu'aucune étape avec un order_number supérieur n'existe
        $this->assertNull(
            Step::where('riddle_id', $riddle->id)
                ->where('order_number', '>', 1)
                ->first()
        );

        $service = new GameplayService();
        $result = $service->validateStep($gameSession, $user, 'QRLAST');

        // Vérifier en base la mise à jour du statut
        $this->assertDatabaseHas('game_sessions', [
            'id'     => $gameSession->id,
            'status' => 'completed',
        ]);

        $this->assertIsArray($result);
        $this->assertTrue($result['game_completed']);
        $this->assertArrayHasKey('game_session', $result);

        // Vérifier que le GameSession a bien été mis à jour en status "completed"
        $freshSession = $result['game_session'];
        $this->assertEquals('completed', $freshSession->status);
    }
}