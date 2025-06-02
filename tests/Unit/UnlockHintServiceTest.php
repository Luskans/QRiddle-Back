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

class UnlockHintServiceTest extends TestCase
{
    use RefreshDatabase, GameTestHelper;


    /**
     * Cas : L'utilisateur n'est pas autorisé à débloquer un indice.
     */
    public function test_unlockHint_unauthorized_user()
    {
        $user = User::factory()->create(['id' => 1]);
        $otherUser = User::factory()->create(['id' => 2]);
        $riddle = Riddle::factory()->create();

        $gameSession = $this->createGameSessionWithActiveStep($user, $riddle, 'qr_code');
        $service = new GameplayService();

        $this->expectExceptionMessage('Utilisateur non autorisé.');
        $this->expectExceptionCode(Response::HTTP_FORBIDDEN);

        $service->unlockHint($gameSession, $otherUser, 2);
    }

    /**
     * Cas : Aucune étape active (la session ne contient pas de latestActiveSessionStep).
     */
    public function test_unlockHint_no_active_step()
    {
        $user = User::factory()->create();
        $gameSession = GameSession::factory()->create([
            'user_id' => $user->id,
            'status'  => 'active'
        ]);
        // Pas de relation latestActiveSessionStep définie

        $service = new GameplayService();

        $this->expectExceptionMessage("L'étape est déjà terminée ou abandonnée.");
        $this->expectExceptionCode(Response::HTTP_UNPROCESSABLE_ENTITY);

        $service->unlockHint($gameSession, $user, 2);
    }

    /**
     * Cas : L'indice demandé est déjà débloqué (hintOrder vaut 1).
     */
    public function test_unlockHint_already_unlocked_when_hintOrder_is_1()
    {
        $user = User::factory()->create();
        $riddle = Riddle::factory()->create();
        $gameSession = $this->createGameSessionWithActiveStep($user, $riddle, 'qr_code');

        $service = new GameplayService();

        $this->expectExceptionMessage('Indice déjà déverrouillé.');
        $this->expectExceptionCode(Response::HTTP_BAD_REQUEST);

        $service->unlockHint($gameSession, $user, 1);
    }

    /**
     * Cas : L'indice demandé est déjà débloqué (hintOrder <= extra_hints).
     */
    public function test_unlockHint_already_unlocked_when_hintOrder_leq_extraHints()
    {
        $user = User::factory()->create();
        $riddle = Riddle::factory()->create();
        $gameSession = $this->createGameSessionWithActiveStep($user, $riddle, 'qr_code');
        // Simuler qu'un indice a déjà été débloqué
        $sessionStep = $gameSession->latestActiveSessionStep;
        $sessionStep->extra_hints = 2;
        $sessionStep->save();

        $service = new GameplayService();

        $this->expectExceptionMessage('Indice déjà déverrouillé.');
        $this->expectExceptionCode(Response::HTTP_BAD_REQUEST);

        // Ici, hintOrder vaut 2 qui est <= extra_hints (2)
        $service->unlockHint($gameSession, $user, 2);
    }

    /**
     * Cas : L'indice demandé n'est pas le prochain indice à débloquer (écart supérieur à 2).
     */
    public function test_unlockHint_hintOrder_too_far()
    {
        $user = User::factory()->create();
        $riddle = Riddle::factory()->create();
        $gameSession = $this->createGameSessionWithActiveStep($user, $riddle, 'qr_code');
        // Supposons qu'aucun indice n'a encore été débloqué (extra_hints = 0)
        $service = new GameplayService();

        $this->expectExceptionMessage("Veuillez débloquer l'indice précédent.");
        $this->expectExceptionCode(Response::HTTP_FORBIDDEN);

        // Ici, hintOrder = 3 alors que extra_hints = 0, donc 3 - 0 > 2
        $service->unlockHint($gameSession, $user, 3);
    }

    /**
     * Cas de succès : Débloquer le prochain indice.
     */
    public function test_unlockHint_success()
    {
        $user = User::factory()->create();
        $riddle = Riddle::factory()->create();
        $gameSession = $this->createGameSessionWithActiveStep($user, $riddle, 'qr_code');
        $sessionStep = $gameSession->latestActiveSessionStep;
        // Extra hints initial = 0, nous souhaitons débloquer l'indice d'ordre 2

        $service = new GameplayService();

        // Appel de la méthode unlockHint
        $updatedSession = $service->unlockHint($gameSession, $user, 2);

        // On récupère la session mise à jour en base (via fresh())
        $freshSession = $gameSession->fresh();
        $freshSessionStep = $freshSession->latestActiveSessionStep;

        // L'attribut extra_hints doit avoir été incrémenté de 1
        $this->assertEquals(1, $freshSessionStep->extra_hints);

        // Vérifier que la modification est persistée en base de données
        $this->assertDatabaseHas('session_steps', [
            'id' => $sessionStep->id,
            'extra_hints' => 1,
        ]);

        // On s'assure que la méthode renvoie bien la session actualisée
        $this->assertInstanceOf(GameSession::class, $updatedSession);
    }
}