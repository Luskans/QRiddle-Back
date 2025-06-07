<?php

namespace Tests\Unit;

use App\Models\GameSession;
use App\Models\Riddle;
use App\Models\SessionStep;
use App\Models\Step;
use App\Models\User;
use App\Repositories\Interfaces\GameSessionRepositoryInterface;
use App\Repositories\Interfaces\ReviewRepositoryInterface;
use App\Repositories\Interfaces\SessionStepRepositoryInterface;
use App\Services\GameplayService;
use App\Services\Interfaces\ScoreServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Mockery;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;
use Tests\Traits\GameTestHelper;

class StartGameServiceTest extends TestCase
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
     * Vérifie que si une session active existe déjà, startGame la retourne.
     */
    public function test_startGame_returns_existing_active_session()
    {
        $user = User::factory()->makeOne();
        $riddle = $this->createRiddleWithSteps();

        // Crée une session de jeu active pour ce riddle et cet utilisateur
        $existingSession = GameSession::factory()->makeOne([
            'riddle_id' => $riddle->id,
            'user_id'   => $user->id,
            'status'    => 'active',
            'score'     => 0,
        ]);

        // Créer une requête vide (aucune donnée n'est requise pour un riddle public)
        $request = new Request();

        $resultSession = $this->service->startGame($riddle, $user, $request);

        $this->assertEquals($existingSession->id, $resultSession->id);
    }

    /**
     * Vérifie que pour un riddle public sans session active, une nouvelle session est créée.
     */
    public function test_startGame_creates_new_session_for_public_riddle()
    {
        $user = User::factory()->makeOne();
        $riddle = $this->createRiddleWithSteps();

        // S'assurer qu'aucune session n'existe pour cet utilisateur et cette énigme
        $this->assertNull(
            GameSession::where('riddle_id', $riddle->id)
                ->where('user_id', $user->id)
                ->first()
        );

        $request = new Request();

        // Puisque la méthode utilise DB::transaction, nous pouvons autoriser sa bonne exécution
        $newSession = $this->service->startGame($riddle, $user, $request);

        $this->assertNotNull($newSession);
        $this->assertEquals('active', $newSession->status);
        $this->assertEquals($riddle->id, $newSession->riddle_id);

        // Vérifie qu'un SessionStep a été créé (pour la première étape)
        $this->assertTrue($newSession->sessionSteps()->exists());
    }

    /**
     * Vérifie qu'une exception est levée si l'énigme est privée et que le mot de passe est incorrect.
     */
    public function test_startGame_throws_exception_for_private_riddle_with_wrong_password()
    {
        $user = User::factory()->makeOne();
        // Créer une énigme privée avec un mot de passe
        $riddle = Riddle::factory()->makeOne([
            'is_private' => true,
            'password'   => 'secret123',
        ]);

        // Créer au moins une étape pour l'énigme
        Step::factory()->makeOne([
            'riddle_id'    => $riddle->id,
            'order_number' => 1,
        ]);

        // Simuler une requête contenant un mauvais mot de passe
        $request = new Request([
            'password' => 'wrongpass'
        ]);

        $this->expectExceptionMessage('Mot de passe incorrect.');
        $this->expectExceptionCode(Response::HTTP_FORBIDDEN);

        $this->service->startGame($riddle, $user, $request);
    }

    /**
     * Vérifie qu'une exception est levée si l'énigme ne contient aucune étape.
     */
    public function test_startGame_throws_exception_if_no_steps_in_riddle()
    {
        $user = User::factory()->makeOne();
        // Créer une énigme publique sans étapes
        $riddle = Riddle::factory()->makeOne([
            'is_private' => false,
            'password'   => null,
        ]);

        $request = new Request();

        $this->expectExceptionMessage('Cette énigme ne contient aucune étape.');
        $this->expectExceptionCode(Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->service->startGame($riddle, $user, $request);
    }

    /**
     * Vérifie qu'une ancienne session non active est supprimée et qu'une nouvelle session est créée.
     */
    public function test_startGame_deletes_non_active_existing_session_and_creates_new_one()
    {
        $user = User::factory()->makeOne();
        $riddle = $this->createRiddleWithSteps();

        // Crée une ancienne session (par exemple abandonnée)
        $oldSession = GameSession::factory()->makeOne([
            'riddle_id' => $riddle->id,
            'user_id'   => $user->id,
            'status'    => 'abandoned',
        ]);

        $request = new Request();

        $newSession = $this->service->startGame($riddle, $user, $request);

        $this->assertNotEquals($oldSession->id, $newSession->id);
        $this->assertDatabaseMissing('game_sessions', ['id' => $oldSession->id]);
        $this->assertEquals('active', $newSession->status);
    }

    public function test_startGame_abandons_other_active_sessions_for_user()
    {
        $user = User::factory()->makeOne();

        // Une autre session active pour un autre riddle
        $otherRiddle = $this->createRiddleWithSteps();
        $otherSession = GameSession::factory()->makeOne([
            'riddle_id' => $otherRiddle->id,
            'user_id'   => $user->id,
            'status'    => 'active',
        ]);
        $step = SessionStep::factory()->makeOne([
            'game_session_id' => $otherSession->id,
            'status' => 'active',
            'start_time' => now(),
        ]);

        $riddle = $this->createRiddleWithSteps();
        $request = new Request();

        $this->service->startGame($riddle, $user, $request);
    }
}