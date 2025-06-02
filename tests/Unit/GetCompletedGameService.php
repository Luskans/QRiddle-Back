<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Riddle;
use App\Models\GameSession;
use App\Services\GameplayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;
use Tests\Traits\GameTestHelper;

class GetCompletedGameServiceTest extends TestCase
{
    use RefreshDatabase, GameTestHelper;

    
    /**
     * Vérifie que getCompletedGame retourne bien les données attendues
     * lorsque la session est complétée et l'utilisateur est autorisé.
     */
    public function test_getCompletedGame_returns_data_for_completed_game()
    {
        $user = User::factory()->create();
        $riddle = Riddle::factory()->create();
        
        $gameSession = $this->createCompletedGameSession($user, $riddle);
        $service = new GameplayService();

        $result = $service->getCompletedGame($gameSession, $user);

        // Vérifier que le résultat est un tableau contenant les clés attendues.
        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('riddle_id', $result);
        $this->assertArrayHasKey('score', $result);
        $this->assertArrayHasKey('session_steps', $result);

        // Vérifier certaines valeurs.
        $this->assertEquals($gameSession->id, $result['id']);
        $this->assertEquals($riddle->id, $result['riddle_id']);
        $this->assertEquals(150, $result['score']);
        $this->assertCount(2, $result['session_steps']);

        // Vérifications en base
        $this->assertDatabaseHas('game_sessions', [
            'id'     => $gameSession->id,
            'status' => 'completed',
            'score'  => 150,
        ]);

        $this->assertDatabaseHas('session_steps', [
            'game_session_id' => $gameSession->id,
            'extra_hints'     => 1,
        ]);
    }

    /**
     * Vérifie qu'une exception est levée lorsque l'utilisateur passé
     * n'est pas le propriétaire de la session.
     */
    public function test_getCompletedGame_unauthorized_user_throws_exception()
    {
        $user = User::factory()->create(['id' => 1]);
        $otherUser = User::factory()->create(['id' => 2]);
        $riddle = Riddle::factory()->create();

        $gameSession = $this->createCompletedGameSession($user, $riddle);
        $service = new GameplayService();

        $this->expectExceptionMessage('Utilisateur non autorisé.');
        $this->expectExceptionCode(Response::HTTP_FORBIDDEN);

        $service->getCompletedGame($gameSession, $otherUser);
    }

    /**
     * Vérifie qu'une exception est levée lorsque la session n'est pas complétée.
     */
    public function test_getCompletedGame_non_completed_session_throws_exception()
    {
        $user = User::factory()->create();
        $riddle = Riddle::factory()->create();

        // Créer une session avec status "active" au lieu de "completed"
        $gameSession = GameSession::factory()->create([
            'riddle_id' => $riddle->id,
            'user_id'   => $user->id,
            'status'    => 'active'
        ]);

        $service = new GameplayService();

        $this->expectExceptionMessage("L'énigme n'est pas encore réussie.");
        $this->expectExceptionCode(Response::HTTP_UNPROCESSABLE_ENTITY);

        $service->getCompletedGame($gameSession, $user);
    }
}