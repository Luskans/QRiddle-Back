<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\GameSession;
use App\Models\Riddle;
use App\Models\User;
use App\Repositories\Interfaces\GameSessionRepositoryInterface;
use App\Repositories\Interfaces\ReviewRepositoryInterface;
use App\Repositories\Interfaces\SessionStepRepositoryInterface;
use App\Services\GameplayService;
use App\Services\Interfaces\ScoreServiceInterface;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\Traits\GameTestHelper;

class GetCurrentGameServiceTest extends TestCase
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
     * Vérifie que getCurrentGame retourne bien les données attendues lorsque tout fonctionne.
     */
    public function test_getCurrentGame_returns_correct_data()
    {
        $user = User::factory()->make();
        $riddle = Riddle::factory()->make();
        $gameSession = $this->createActiveGameSessionWithStep($user, $riddle);

        $result = $this->service->getCurrentGame($gameSession, $user);

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
        $user = User::factory()->makeOne();
        $otherUser = User::factory()->makeOne();
        $riddle = Riddle::factory()->makeOne();

        // Créer une session appartenant à $user
        $gameSession = GameSession::factory()->makeOne([
            'riddle_id' => $riddle->id,
            'user_id'   => $user->id,
            'status'    => 'active'
        ]);

        $this->expectExceptionMessage('Utilisateur non autorisé.');
        $this->expectExceptionCode(Response::HTTP_FORBIDDEN);

        $this->service->getCurrentGame($gameSession, $otherUser);
    }

    /**
     * Vérifie qu'une exception est levée lorsque la session n'est pas active.
     */
    public function test_getCurrentGame_throws_exception_if_session_not_active()
    {
        $user = User::factory()->makeOne();
        $riddle = Riddle::factory()->makeOne();

        // Créer une session de jeu non active (exemple : terminée)
        $gameSession = GameSession::factory()->makeOne([
            'riddle_id' => $riddle->id,
            'user_id'   => $user->id,
            'status'    => 'completed'
        ]);

        $this->expectExceptionMessage('La partie est déjà terminée ou abandonnée.');
        $this->expectExceptionCode(Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->service->getCurrentGame($gameSession, $user);
    }

    /**
     * Vérifie qu'une exception est levée si aucun step actif n'est trouvé (typiquement si la partie est finie).
     */
    public function test_getCurrentGame_throws_exception_if_no_active_step_found()
    {
        $user = User::factory()->makeOne();
        $riddle = Riddle::factory()->makeOne();

        // Créer une session active sans définir de latestActiveSessionStep
        $gameSession = GameSession::factory()->create([
            'riddle_id' => $riddle->id,
            'user_id'   => $user->id,
            'status'    => 'active'
        ]);

        $this->expectExceptionMessage('La partie est déjà terminée ou abandonnée.');
        $this->expectExceptionCode(Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->service->getCurrentGame($gameSession, $user);
    }
}