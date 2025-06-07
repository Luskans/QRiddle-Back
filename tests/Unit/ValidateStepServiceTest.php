<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Riddle;
use App\Models\Step;
use App\Repositories\Interfaces\GameSessionRepositoryInterface;
use App\Repositories\Interfaces\ReviewRepositoryInterface;
use App\Repositories\Interfaces\SessionStepRepositoryInterface;
use App\Services\GameplayService;
use App\Services\Interfaces\ScoreServiceInterface;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\Traits\GameTestHelper;

class ValidateStepServiceTest extends TestCase
{
    use GameTestHelper;

    protected $gameSessionRepository;
    protected $sessionStepRepository;
    protected $reviewRepository;
    protected $scoreService;
    protected $service;

    public function setUp(): void
    {
        parent::setUp();

        $this->gameSessionRepository = Mockery::mock(GameSessionRepositoryInterface::class);
        $this->sessionStepRepository = Mockery::mock(SessionStepRepositoryInterface::class);
        $this->reviewRepository = Mockery::mock(ReviewRepositoryInterface::class);
        $this->scoreService = Mockery::mock(ScoreServiceInterface::class);

        $this->service = new GameplayService(
            $this->gameSessionRepository,
            $this->sessionStepRepository,
            $this->reviewRepository,
            $this->scoreService
        );
    }

    public function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test : L'utilisateur n'est pas autorisé à valider l'étape.
     */
    public function test_validateStep_unauthorized_user()
    {
        $user = User::factory()->makeOne(['id' => 1]);
        $otherUser = User::factory()->makeOne(['id' => 2]);
        $riddle = Riddle::factory()->makeOne();
        $gameSession = $this->createGameSessionWithActiveStep($user, $riddle, 'QRVALIDE');

        $this->expectExceptionMessage('Utilisateur non autorisé.');
        $this->expectExceptionCode(Response::HTTP_FORBIDDEN);

        $this->service->validateStep($gameSession, $otherUser, 'QRVALIDE');
    }

    /**
     * Test : Le QR code fourni est incorrect.
     */
    public function test_validateStep_invalid_qrCode()
    {
        $user = User::factory()->makeOne();
        $riddle = Riddle::factory()->makeOne();
        $gameSession = $this->createGameSessionWithActiveStep($user, $riddle, 'QRVALIDE');

        $this->expectExceptionMessage('QR code non valide.');
        $this->expectExceptionCode(Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->service->validateStep($gameSession, $user, 'QRMauvais');
    }

    /**
     * Test : La validation d'une étape intermédiaire crée un nouveau SessionStep pour l'étape suivante,
     * et retourne game_completed = false.
     */
    public function test_validateStep_with_next_step()
    {
        $user = User::factory()->makeOne();
        $riddle = Riddle::factory()->makeOne();

        // Créer la première étape active avec QR code 'QR1'
        $gameSession = $this->createGameSessionWithActiveStep($user, $riddle, 'QR1', 1);

        // Créer une deuxième étape qui sera la prochaine étape (order_number = 2)
        $nextStep = Step::factory()->makeOne([
            'riddle_id'    => $riddle->id,
            'order_number' => 2,
            'qr_code'      => 'QR2',
        ]);

        $result = $this->service->validateStep($gameSession, $user, 'QR1');

        // On s'attend à un tableau avec 'game_completed' à false et la session mise à jour
        $this->assertIsArray($result);
        $this->assertFalse($result['game_completed']);
        $this->assertArrayHasKey('game_session', $result);

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
        $user = User::factory()->makeOne();
        $riddle = Riddle::factory()->makeOne();

        // Création d'une session pour une unique étape (aucune étape suivante)
        $gameSession = $this->createGameSessionWithActiveStep($user, $riddle, 'QRLAST', 1);

        // Assurer qu'aucune étape avec un order_number supérieur n'existe
        $this->assertNull(
            Step::where('riddle_id', $riddle->id)
                ->where('order_number', '>', 1)
                ->first()
        );

        $result = $this->service->validateStep($gameSession, $user, 'QRLAST');

        $this->assertIsArray($result);
        $this->assertTrue($result['game_completed']);
        $this->assertArrayHasKey('game_session', $result);

        // Vérifier que le GameSession a bien été mis à jour en status "completed"
        $freshSession = $result['game_session'];
        $this->assertEquals('completed', $freshSession->status);
    }
}