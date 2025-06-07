<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Riddle;
use App\Models\GameSession;
use App\Repositories\Interfaces\GameSessionRepositoryInterface;
use App\Repositories\Interfaces\ReviewRepositoryInterface;
use App\Repositories\Interfaces\SessionStepRepositoryInterface;
use App\Services\GameplayService;
use App\Services\Interfaces\ScoreServiceInterface;
use Mockery;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;
use Tests\Traits\GameTestHelper;

class GetCompletedGameServiceTest extends TestCase
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
     * Vérifie que getCompletedGame retourne bien les données attendues
     * lorsque la session est complétée et l'utilisateur est autorisé.
     */
    public function test_getCompletedGame_returns_data_for_completed_game()
    {
        $user = User::factory()->makeOne();
        $riddle = Riddle::factory()->makeOne();
        $gameSession = $this->createCompletedGameSession($user, $riddle);

        $result = $this->service->getCompletedGame($gameSession, $user);

        // Vérifier que le résultat est un tableau contenant les clés attendues.
        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('riddle_id', $result);
        $this->assertArrayHasKey('score', $result);
        $this->assertArrayHasKey('has_reviewed', $result);
        $this->assertArrayHasKey('session_steps', $result);

        // Vérifier certaines valeurs.
        $this->assertEquals($gameSession->id, $result['id']);
        $this->assertEquals($riddle->id, $result['riddle_id']);
        $this->assertEquals(150, $result['score']);
        $this->assertFalse($result['has_reviewed']);
        $this->assertCount(2, $result['session_steps']);
    }

    /**
     * Vérifie qu'une exception est levée lorsque l'utilisateur passé
     * n'est pas le propriétaire de la session.
     */
    public function test_getCompletedGame_unauthorized_user_throws_exception()
    {
        $user = User::factory()->makeOne(['id' => 1]);
        $otherUser = User::factory()->makeOne(['id' => 2]);
        $riddle = Riddle::factory()->makeOne();
        $gameSession = $this->createCompletedGameSession($user, $riddle);

        $this->expectExceptionMessage('Utilisateur non autorisé.');
        $this->expectExceptionCode(Response::HTTP_FORBIDDEN);

        $this->service->getCompletedGame($gameSession, $otherUser);
    }

    /**
     * Vérifie qu'une exception est levée lorsque la session n'est pas complétée.
     */
    public function test_getCompletedGame_non_completed_session_throws_exception()
    {
        $user = User::factory()->makeOne();
        $riddle = Riddle::factory()->makeOne();

        // Créer une session avec status "active" au lieu de "completed"
        $gameSession = GameSession::factory()->makeOne([
            'riddle_id' => $riddle->id,
            'user_id'   => $user->id,
            'status'    => 'active'
        ]);

        $this->expectExceptionMessage("L'énigme n'est pas encore réussie.");
        $this->expectExceptionCode(Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->service->getCompletedGame($gameSession, $user);
    }
}