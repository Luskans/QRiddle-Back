<?php

// namespace Tests\Unit;

// use Tests\TestCase;
// use App\Services\GameplayService;
// use App\Repositories\Interfaces\GameSessionRepositoryInterface;
// use App\Repositories\Interfaces\SessionStepRepositoryInterface;
// use App\Repositories\Interfaces\ReviewRepositoryInterface;
// use App\Services\Interfaces\ScoreServiceInterface;
// use Illuminate\Http\Request;
// use Illuminate\Support\Facades\DB;
// use Illuminate\Support\Facades\Validator;
// use App\Models\User;
// use App\Models\Riddle;
// use App\Models\GameSession;
// use App\Models\Hint;
// use App\Models\SessionStep;
// use App\Models\Step;
// use Mockery;
// use Symfony\Component\HttpFoundation\Response;

// class GameplayServiceTest extends TestCase
// {
//     protected $gameSessionRepository;
//     protected $sessionStepRepository;
//     protected $reviewRepository;
//     protected $scoreService;
//     protected $service;

//     public function setUp(): void
//     {
//         parent::setUp();

//         $this->gameSessionRepository = Mockery::mock(GameSessionRepositoryInterface::class);
//         $this->sessionStepRepository = Mockery::mock(SessionStepRepositoryInterface::class);
//         $this->reviewRepository = Mockery::mock(ReviewRepositoryInterface::class);
//         $this->scoreService = Mockery::mock(ScoreServiceInterface::class);

//         $this->service = new GameplayService(
//             $this->gameSessionRepository,
//             $this->sessionStepRepository,
//             $this->reviewRepository,
//             $this->scoreService
//         );
//     }

//     public function tearDown(): void
//     {
//         Mockery::close();
//         parent::tearDown();
//     }

//     public function test_start_game_returns_existing_session_if_exists()
//     {
//         $user = new User(['id' => 1]);
//         $riddle = new Riddle(['id' => 10]);
//         $request = Request::create('/');

//         $existingSession = new GameSession(['id' => 123]);

//         $this->gameSessionRepository
//             ->shouldReceive('getActiveSessionForRiddleAndUser')
//             ->with($riddle->id, $user->id)
//             ->andReturn($existingSession);

//         $result = $this->service->startGame($riddle, $user, $request);

//         $this->assertEquals($existingSession, $result);
//     }

//     public function test_start_game_throws_exception_if_private_and_wrong_password()
//     {
//         $this->expectException(\Exception::class);
//         $this->expectExceptionMessage('Mot de passe incorrect.');

//         $user = new User(['id' => 1]);
//         $riddle = new Riddle(['id' => 10, 'is_private' => true, 'password' => 'secret']);
//         $request = Request::create('/', 'POST', ['password' => 'wrong']);

//         $this->gameSessionRepository
//             ->shouldReceive('getActiveSessionForRiddleAndUser')
//             ->andReturn(null);

//         $this->expectExceptionCode(403);

//         $this->service->startGame($riddle, $user, $request);
//     }

//     public function test_start_game_creates_new_session()
//     {
//         DB::shouldReceive('transaction')->andReturnUsing(function ($closure) {
//             return $closure();
//         });

//         $user = new User(['id' => 1]);
//         $riddle = Mockery::mock(Riddle::class)->makePartial();
//         $riddle->id = 10;
//         $riddle->is_private = false;

//         $request = Request::create('/');

//         $this->gameSessionRepository
//             ->shouldReceive('getActiveSessionForRiddleAndUser')
//             ->once()
//             ->andReturn(null);

//         $this->gameSessionRepository
//             ->shouldReceive('abandonAllActiveSessionsForUser')
//             ->once();

//         $gameSession = new GameSession(['id' => 99]);
//         $this->gameSessionRepository
//             ->shouldReceive('createSession')
//             ->once()
//             ->andReturn($gameSession);

//         $firstStep = new \stdClass();
//         $firstStep->id = 5;

//         $riddle->shouldReceive('steps->orderBy->first')->andReturn($firstStep);

//         $this->sessionStepRepository
//             ->shouldReceive('create')
//             ->once()
//             ->with(Mockery::on(function ($data) use ($gameSession, $firstStep) {
//                 return $data['game_session_id'] === $gameSession->id &&
//                       $data['step_id'] === $firstStep->id;
//             }));

//         $result = $this->service->startGame($riddle, $user, $request);

//         $this->assertEquals($gameSession, $result);
//     }

//     public function test_start_game_validates_password_for_private_riddle()
//     {
//         $user = new User(['id' => 1]);
//         $riddle = new Riddle(['id' => 10, 'is_private' => true, 'password' => 'secret']);
//         $request = Request::create('/', 'POST', ['password' => 'secret']);

//         $this->gameSessionRepository
//             ->shouldReceive('getActiveSessionForRiddleAndUser')
//             ->andReturn(null);

//         $this->gameSessionRepository
//             ->shouldReceive('abandonAllActiveSessionsForUser')
//             ->once();

//         $gameSession = new GameSession(['id' => 99]);
//         $this->gameSessionRepository
//             ->shouldReceive('createSession')
//             ->once()
//             ->andReturn($gameSession);

//         $firstStep = new \stdClass();
//         $firstStep->id = 5;

//         $riddleMock = Mockery::mock($riddle);
//         $riddleMock->shouldReceive('steps->orderBy->first')->andReturn($firstStep);

//         $this->sessionStepRepository
//             ->shouldReceive('create')
//             ->once();

//         $result = $this->service->startGame($riddleMock, $user, $request);

//         $this->assertEquals($gameSession, $result);
//     }

//     public function test_start_game_throws_exception_if_no_steps()
//     {
//         $this->expectExceptionMessage('Cette énigme ne contient aucune étape.');
//         $this->expectExceptionCode(Response::HTTP_UNPROCESSABLE_ENTITY);

//         $user = new User(['id' => 1]);
//         $riddle = Mockery::mock(Riddle::class)->makePartial();
//         $riddle->id = 10;
//         $riddle->is_private = false;
//         $request = Request::create('/');

//         $this->gameSessionRepository
//             ->shouldReceive('getActiveSessionForRiddleAndUser')
//             ->andReturn(null);

//         $this->gameSessionRepository
//             ->shouldReceive('abandonAllActiveSessionsForUser')
//             ->once();

//         $gameSession = new GameSession(['id' => 99]);
//         $this->gameSessionRepository
//             ->shouldReceive('createSession')
//             ->once()
//             ->andReturn($gameSession);

//         $riddle->shouldReceive('steps->orderBy->first')->andReturn(null);

//         $this->service->startGame($riddle, $user, $request);
//     }

//     public function test_abandon_game_throws_if_user_mismatch()
//     {
//         $this->expectExceptionMessage('Utilisateur non autorisé.');
//         $this->expectExceptionCode(403);

//         $session = new GameSession(['user_id' => 1, 'status' => 'active']);
//         $user = new User(['id' => 2]);

//         $this->service->abandonGame($session, $user);
//     }

//     public function test_abandon_game_throws_if_session_not_active()
//     {
//         $this->expectExceptionMessage('La partie est déjà terminée ou abandonnée.');

//         $session = new GameSession(['user_id' => 1, 'status' => 'completed']);
//         $user = new User(['id' => 1]);

//         $this->service->abandonGame($session, $user);
//     }

//     public function test_abandon_game_successfully()
//     {
//         DB::shouldReceive('transaction')->andReturnUsing(function ($closure) {
//             return $closure();
//         });

//         $session = new GameSession(['user_id' => 1, 'status' => 'active']);
//         $user = new User(['id' => 1]);

//         $step = Mockery::mock(SessionStep::class);
//         $session->setRelation('latestActiveSessionStep', $step);

//         $this->gameSessionRepository
//             ->shouldReceive('updateSessionStatus')
//             ->once();

//         $this->sessionStepRepository
//             ->shouldReceive('abandonStep')
//             ->once()
//             ->with($step);

//         $result = $this->service->abandonGame($session, $user);

//         $this->assertEquals($session, $result);
//     }

//     public function test_abandon_game_successfully_abandons_session_and_step()
//     {
//         $user = User::factory()->make(['id' => 1]);
//         $session = Mockery::mock(GameSession::class);
//         $session->user_id = $user->id;
//         $session->status = 'active';

//         $step = Mockery::mock(SessionStep::class);
//         $session->shouldReceive('getAttribute')->with('latestActiveSessionStep')->andReturn($step);

//         $this->gameSessionRepository
//             ->shouldReceive('updateSessionStatus')
//             ->once()
//             ->with($session, 'abandoned');

//         $this->sessionStepRepository
//             ->shouldReceive('abandonStep')
//             ->once()
//             ->with($step);

//         $result = $this->service->abandonGame($session, $user);
//         $this->assertSame($session, $result);
//     }

//     public function test_abandon_game_fails_if_user_not_owner()
//     {
//         $this->expectExceptionMessage('Utilisateur non autorisé.');

//         $user = User::factory()->make(['id' => 2]);
//         $session = new GameSession(['user_id' => 1, 'status' => 'active']);

//         $this->service->abandonGame($session, $user);
//     }

//     public function test_abandon_game_fails_if_status_not_active()
//     {
//         $this->expectExceptionMessage('La partie est déjà terminée ou abandonnée.');

//         $user = User::factory()->make(['id' => 1]);
//         $session = new GameSession(['user_id' => 1, 'status' => 'completed']);

//         $this->service->abandonGame($session, $user);
//     }

//     public function test_get_current_game_returns_correct_step_and_hints()
//     {
//         $user = User::factory()->make(['id' => 1]);

//         $hint1 = new Hint(['id' => 1, 'order_number' => 1, 'type' => 'text', 'content' => 'Indice 1']);
//         $hint2 = new Hint(['id' => 2, 'order_number' => 2, 'type' => 'text', 'content' => 'Indice 2']);

//         $step = new Step(['id' => 10, 'order_number' => 1]);
//         $step->setRelation('hints', collect([$hint1, $hint2]));

//         $sessionStep = new SessionStep([
//             'id' => 100, 'extra_hints' => 1, 'start_time' => now()
//         ]);
//         $stepMock = Mockery::mock($step);
//         $stepMock->shouldReceive('only')->andReturn(['id' => 10, 'order_number' => 1]);

//         $sessionStep->setRelation('step', $stepMock);

//         $riddle = Riddle::factory()->make();
//         $riddle->setRelation('steps', collect([$step]));

//         $session = new GameSession([
//             'user_id' => 1,
//             'status' => 'active'
//         ]);
//         $session->setRelation('latestActiveSessionStep', $sessionStep);
//         $session->setRelation('riddle', $riddle);

//         $result = $this->service->getCurrentGame($session, $user);

//         $this->assertArrayHasKey('hints', $result);
//         $this->assertTrue($result['hints'][0]['unlocked']);
//         $this->assertTrue($result['hints'][1]['unlocked']);
//     }

//     public function test_get_current_game_fails_if_not_owner()
//     {
//         $this->expectExceptionMessage('Utilisateur non autorisé.');

//         $session = new GameSession(['user_id' => 1, 'status' => 'active']);
//         $user = User::factory()->make(['id' => 2]);

//         $this->service->getCurrentGame($session, $user);
//     }

//     public function test_get_completed_game_returns_summary()
//     {
//         $user = User::factory()->make(['id' => 1]);
//         $riddle = Riddle::factory()->make(['id' => 5]);

//         $step1 = new Step(['id' => 1, 'order_number' => 1]);
//         $step2 = new Step(['id' => 2, 'order_number' => 2]);

//         $sessionStep1 = new SessionStep(['step_id' => 1, 'duration' => 30, 'extra_hints' => 1]);
//         $sessionStep2 = new SessionStep(['step_id' => 2, 'duration' => 50, 'extra_hints' => 2]);

//         $riddle->setRelation('steps', collect([$step1, $step2]));
//         $session = new GameSession([
//             'user_id' => 1,
//             'status' => 'completed',
//         ]);
//         $session->setRelation('sessionSteps', collect([$sessionStep1, $sessionStep2]));
//         $session->setRelation('riddle', $riddle);

//         $result = $this->service->getCompletedGame($session, $user);

//         $this->assertEquals(80, $result['duration']);
//         $this->assertEquals(3, $result['extra_hints']);
//     }

//     public function test_get_completed_game_includes_review_status()
//     {
//         $user = User::factory()->make(['id' => 1]);
//         $riddle = Riddle::factory()->make(['id' => 5]);

//         $sessionStep1 = new SessionStep(['step_id' => 1, 'duration' => 30, 'extra_hints' => 1]);
//         $sessionStep2 = new SessionStep(['step_id' => 2, 'duration' => 50, 'extra_hints' => 2]);

//         $session = Mockery::mock(GameSession::class);
//         $session->user_id = 1;
//         $session->riddle_id = 5;
//         $session->status = 'completed';
//         $session->score = 100;
//         $session->shouldReceive('getTotalDuration')->andReturn(80);
//         $session->shouldReceive('getAttribute')->with('sessionSteps')->andReturn(collect([$sessionStep1, $sessionStep2]));
//         $session->shouldReceive('sessionSteps')->andReturnSelf();
//         $session->shouldReceive('select')->andReturnSelf();
//         $session->shouldReceive('get')->andReturn(collect([$sessionStep1, $sessionStep2]));

//         $this->reviewRepository
//             ->shouldReceive('userHasReviewedRiddle')
//             ->once()
//             ->with(5, 1)
//             ->andReturn(true);

//         $result = $this->service->getCompletedGame($session, $user);

//         $this->assertEquals(80, $result['duration']);
//         $this->assertEquals(100, $result['score']);
//         $this->assertTrue($result['has_reviewed']);
//     }

//     public function test_get_completed_game_fails_if_not_owner()
//     {
//         $this->expectExceptionMessage('Utilisateur non autorisé.');

//         $user = User::factory()->make(['id' => 2]);
//         $session = new GameSession(['user_id' => 1, 'status' => 'completed']);

//         $this->service->getCompletedGame($session, $user);
//     }

//     public function test_unlock_hint_adds_extra_hint()
//     {
//         $user = User::factory()->make(['id' => 1]);

//         $step = new Step(['id' => 1]);
//         $hint1 = new Hint(['id' => 1, 'order_number' => 1]);
//         $hint2 = new Hint(['id' => 2, 'order_number' => 2]);
//         $step->setRelation('hints', collect([$hint1, $hint2]));

//         $sessionStep = new SessionStep(['extra_hints' => 0]);
//         $sessionStep->setRelation('step', $step);

//         $session = new GameSession(['user_id' => 1]);
//         $session->setRelation('latestActiveSessionStep', $sessionStep);

//         $this->sessionStepRepository
//             ->shouldReceive('incrementExtraHints')
//             ->once()
//             ->with($sessionStep);

//         $result = $this->service->unlockHint($session, $user, 2);

//         $this->assertEquals($hint1->id, $result->id);
//     }

//     public function test_unlock_hint_validates_hint_order_number()
//     {
//         $user = User::factory()->make(['id' => 1]);

//         $step = new Step(['id' => 1]);
//         $hint1 = new Hint(['id' => 1, 'order_number' => 1]);
//         $hint2 = new Hint(['id' => 2, 'order_number' => 2]);
//         $step->setRelation('hints', collect([$hint1, $hint2]));

//         $sessionStep = new SessionStep(['extra_hints' => 0]);
//         $sessionStep->setRelation('step', $step);

//         $session = Mockery::mock(GameSession::class);
//         $session->user_id = 1;
//         $session->shouldReceive('getAttribute')->with('latestActiveSessionStep')->andReturn($sessionStep);
//         $session->shouldReceive('fresh')->andReturn($session);

//         $this->sessionStepRepository
//             ->shouldReceive('incrementExtraHints')
//             ->once()
//             ->with($sessionStep);

//         $result = $this->service->unlockHint($session, $user, 2);

//         $this->assertSame($session, $result);
//     }

//     public function test_unlock_hint_fails_if_hint_already_unlocked()
//     {
//         $this->expectExceptionMessage('Indice déjà déverrouillé.');
//         $this->expectExceptionCode(Response::HTTP_BAD_REQUEST);

//         $user = User::factory()->make(['id' => 1]);
//         $sessionStep = new SessionStep(['extra_hints' => 1]);
//         $session = new GameSession(['user_id' => 1]);
//         $session->setRelation('latestActiveSessionStep', $sessionStep);

//         $this->service->unlockHint($session, $user, 1);
//     }

//     public function test_unlock_hint_fails_if_hint_order_too_high()
//     {
//         $this->expectExceptionMessage('Veuillez débloquer l\'indice précédent.');
//         $this->expectExceptionCode(Response::HTTP_FORBIDDEN);

//         $user = User::factory()->make(['id' => 1]);
//         $sessionStep = new SessionStep(['extra_hints' => 0]);
//         $session = new GameSession(['user_id' => 1]);
//         $session->setRelation('latestActiveSessionStep', $sessionStep);

//         $this->service->unlockHint($session, $user, 3);
//     }

//     public function test_unlock_hint_fails_if_no_more_hints()
//     {
//         $this->expectExceptionMessage('Tous les indices ont déjà été débloqués.');

//         $user = User::factory()->make(['id' => 1]);

//         $step = new Step();
//         $step->setRelation('hints', collect([
//             new Hint(['order_number' => 1]),
//             new Hint(['order_number' => 2]),
//         ]));

//         $sessionStep = new SessionStep(['extra_hints' => 2]);
//         $sessionStep->setRelation('step', $step);

//         $session = new GameSession(['user_id' => 1]);
//         $session->setRelation('latestActiveSessionStep', $sessionStep);

//         $this->service->unlockHint($session, $user, 2);
//     }

//     public function test_validate_step_returns_correct_response_format()
//     {
//         $user = User::factory()->make(['id' => 1]);

//         $step = new Step(['id' => 1, 'order_number' => 1, 'qr_code' => 'valid_qr']);
//         $step->setRelation('hints', collect([]));

//         $sessionStep = Mockery::mock(SessionStep::class);
//         $sessionStep->shouldReceive('getAttribute')->with('step')->andReturn($step);
//         $sessionStep->shouldReceive('update')
//           ->once()
//           ->with(Mockery::subset(['status' => 'completed', 'end_time' => Mockery::any()]))
//           ->andReturnSelf();

//         $nextStep = new Step(['id' => 2, 'order_number' => 2]);
//         $riddle = Mockery::mock(Riddle::class);
//         $riddle->id = 5;
//         $riddle->shouldReceive('steps')->andReturnSelf();
//         $riddle->shouldReceive('where')->with('order_number', '>', $step->order_number)->andReturnSelf();
//         $riddle->shouldReceive('orderBy')->with('order_number')->andReturnSelf();
//         $riddle->shouldReceive('first')->andReturn($nextStep);

//         $session = Mockery::mock(GameSession::class);
//         $session->user_id = $user->id;
//         $session->id = 99;
//         $session->shouldReceive('getAttribute')->with('latestActiveSessionStep')->andReturn($sessionStep);
//         $session->shouldReceive('getAttribute')->with('riddle')->andReturn($riddle);
//         $session->shouldReceive('fresh')->andReturn($session);

//         $this->sessionStepRepository
//             ->shouldReceive('create')
//             ->once()
//             ->with(Mockery::subset([
//                 'game_session_id' => 99,
//                 'step_id' => $nextStep->id,
//                 'status' => 'active'
//             ]));

//         $result = $this->service->validateStep($session, $user, 'valid_qr');

//         $this->assertIsArray($result);
//         $this->assertArrayHasKey('game_completed', $result);
//         $this->assertArrayHasKey('game_session', $result);
//         $this->assertFalse($result['game_completed']);
//         $this->assertSame($session, $result['game_session']);
//     }

//     public function test_validate_step_successfully_validates_step()
//     {
//         $user = User::factory()->make(['id' => 1]);

//         $step = new Step(['id' => 1]);
//         $step->setRelation('hints', collect([]));

//         $sessionStep = Mockery::mock(SessionStep::class);
//         $sessionStep->shouldReceive('getAttribute')->with('step')->andReturn($step);
//         $sessionStep->shouldReceive('getAttribute')->with('is_validated')->andReturn(false);
//         $sessionStep->shouldReceive('update')
//           ->once()
//           ->with(Mockery::subset(['status' => 'completed', 'end_time' => Mockery::any()]))
//           ->andReturnSelf();

//         $nextStep = new Step(['id' => 2]);
//         $riddle = new Riddle(['id' => 5]);
//         $riddle->setRelation('steps', collect([$step, $nextStep]));

//         $session = new GameSession(['user_id' => 1]);
//         $session->setRelation('riddle', $riddle);
//         $session->setRelation('latestActiveSessionStep', $sessionStep);

//         $this->sessionStepRepository
//             ->shouldReceive('validateStep')
//             ->once()
//             ->with($sessionStep)
//             ->andReturn($sessionStep);

//         $this->sessionStepRepository
//             ->shouldReceive('createNextStep')
//             ->once()
//             ->with($session, $nextStep);

//         $this->gameSessionRepository
//             ->shouldReceive('updateSessionStatus')
//             ->never();

//         $result = $this->service->validateStep($session, $user);

//         $this->assertSame($sessionStep, $result);
//     }

//     public function test_validate_step_ends_game_if_last_step()
//     {
//         $user = User::factory()->make(['id' => 1]);

//         $step = new Step(['id' => 2, 'order_number' => 2, 'qr_code' => 'valid_qr']);
//         $step->setRelation('hints', collect([]));

//         $sessionStep = Mockery::mock(SessionStep::class);
//         $sessionStep->shouldReceive('getAttribute')->with('step')->andReturn($step);
//         $sessionStep->shouldReceive('getAttribute')->with('is_validated')->andReturn(false);
//         $sessionStep->shouldReceive('update')
//           ->once()
//           ->with(Mockery::subset(['status' => 'completed', 'end_time' => Mockery::any()]))
//           ->andReturnSelf();

//         $riddle = new Riddle(['id' => 5]);
//         $riddle->setRelation('steps', collect([
//             new Step(['id' => 1, 'order_number' => 1]),
//             $step
//         ]));

//         $session = new GameSession(['user_id' => 1]);
//         $session->setRelation('riddle', $riddle);
//         $session->setRelation('latestActiveSessionStep', $sessionStep);

//         $this->sessionStepRepository
//             ->shouldReceive('validateStep')
//             ->once()
//             ->with($sessionStep)
//             ->andReturn($sessionStep);

//         $this->gameSessionRepository
//             ->shouldReceive('updateSessionStatus')
//             ->once()
//             ->with($session, 'completed');

//         $this->sessionStepRepository
//             ->shouldReceive('createNextStep')
//             ->never();

//         $result = $this->service->validateStep($session, $user, 'valid_qr');

//         $this->assertSame($sessionStep, $result);
//     }

//     public function test_validate_step_fails_with_invalid_qr_code()
//     {
//         $this->expectExceptionMessage('QR code non valide.');
//         $this->expectExceptionCode(Response::HTTP_UNPROCESSABLE_ENTITY);

//         $user = User::factory()->make(['id' => 1]);

//         $step = new Step(['id' => 1, 'qr_code' => 'valid_qr']);
        
//         $sessionStep = Mockery::mock(SessionStep::class);
//         $sessionStep->shouldReceive('getAttribute')->with('step')->andReturn($step);
//         $sessionStep->shouldReceive('update')
//           ->once()
//           ->with(Mockery::subset(['status' => 'completed', 'end_time' => Mockery::any()]))
//           ->andReturnSelf();

//         $session = Mockery::mock(GameSession::class);
//         $session->user_id = $user->id;
//         $session->shouldReceive('getAttribute')->with('latestActiveSessionStep')->andReturn($sessionStep);

//         $this->service->validateStep($session, $user, 'invalid_qr');
//     }

// }
