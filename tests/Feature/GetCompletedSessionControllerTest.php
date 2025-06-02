<?php
namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\GameSession;
use App\Models\Review;
use App\Models\Riddle;
use App\Models\SessionStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;

class GetCompletedSessionControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test que la route GET /game/{gameSession}/completed retourne la session complétée avec succès.
     */
    public function test_getCompletedSession_returns_completed_session_data()
    {
        $user = User::factory()->create();
        $riddle = Riddle::factory()->create();

        $gameSession = GameSession::factory()->create([
            'user_id' => $user->id,
            'riddle_id' => $riddle->id,
            'status' => 'completed',
            'score' => 42,
        ]);

        // Création de steps liés à la session
        SessionStep::factory()->count(3)->create([
            'game_session_id' => $gameSession->id,
            'start_time' => now()->subMinutes(10),
            'end_time' => now(),
            'extra_hints' => 1,
        ]);

        // Ajouter un review pour ce user et cette énigme
        Review::factory()->create([
            'user_id' => $user->id,
            'riddle_id' => $riddle->id,
        ]);

        // Mock getTotalDuration pour retourner une durée arbitraire
        // $this->partialMock(GameSession::class, function ($mock) use ($gameSession) {
        //     $mock->shouldReceive('getTotalDuration')->andReturn(3600);
        // });

        /** @var User $user */
        $this->actingAs($user);

        $response = $this->getJson("/api/game/{$gameSession->id}/complete");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'riddle_id',
                    'score',
                    'duration',
                    'has_reviewed',
                    'session_steps' => [
                        '*' => ['id', 'game_session_id', 'start_time', 'end_time', 'extra_hints']
                    ],
                ],
            ])
            ->assertJsonFragment([
                'id' => $gameSession->id,
                'riddle_id' => $riddle->id,
                'score' => 42,
                'has_reviewed' => true,
                'duration' => 1800,
            ]);
    }

    /**
     * Test que la route retourne une erreur si l'utilisateur n'est pas le propriétaire de la session.
     */
    public function test_getCompletedSession_fails_if_user_not_owner()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $gameSession = GameSession::factory()->create([
            'user_id' => $user->id,
            'status' => 'completed',
        ]);

        /** @var User $otherUser */
        $this->actingAs($otherUser);

        $response = $this->getJson(route('game.completed-session', ['gameSession' => $gameSession->id]));

        $response->assertStatus(Response::HTTP_FORBIDDEN)
            ->assertJson(['message' => 'Utilisateur non autorisé.']);
    }

    /**
     * Test que la route retourne une erreur si la session n'est pas complétée.
     */
    public function test_getCompletedSession_fails_if_session_not_completed()
    {
        $user = User::factory()->create();

        $gameSession = GameSession::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        /** @var User $user */
        $this->actingAs($user);

        $response = $this->getJson(route('game.completed-session', ['gameSession' => $gameSession->id]));

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJson(['message' => "L'énigme n'est pas encore réussie."]);
    }
}
