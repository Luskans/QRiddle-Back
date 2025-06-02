<?php
namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Riddle;
use App\Models\GameSession;
use App\Models\SessionStep;
use App\Models\Step;
use App\Models\Hint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;

class GetActiveSessionControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test que la route GET /game/{gameSession} retourne la session active avec les données attendues.
     */
    public function test_getActiveSession_returns_active_session_data()
    {
        $user = User::factory()->create();
        $riddle = Riddle::factory()->create();
        $steps = Step::factory()->count(3)->create(['riddle_id' => $riddle->id]);
        $gameSession = GameSession::factory()->create([
            'user_id' => $user->id,
            'riddle_id' => $riddle->id,
            'status' => 'active',
        ]);
        $activeStep = $steps->first();

        $sessionStep = SessionStep::factory()->create([
            'game_session_id' => $gameSession->id,
            'step_id' => $activeStep->id,
            'status' => 'active',
            'extra_hints' => 1,
            'start_time' => now()->subMinutes(10),
        ]);

        // Créer quelques indices pour cette étape
        Hint::factory()->create([
            'step_id' => $activeStep->id,
            'order_number' => 1,
            'type' => 'text',
            'content' => 'Indice 1',
        ]);
        Hint::factory()->create([
            'step_id' => $activeStep->id,
            'order_number' => 2,
            'type' => 'text',
            'content' => 'Indice 2',
        ]);
        Hint::factory()->create([
            'step_id' => $activeStep->id,
            'order_number' => 3,
            'type' => 'text',
            'content' => 'Indice 3',
        ]);

        /** @var User $user */
        $this->actingAs($user);

        $response = $this->getJson(route('game.active-session', ['gameSession' => $gameSession->id]));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'session_step' => ['id', 'extra_hints', 'start_time'],
                    'step' => ['id', 'order_number'],
                    'stepsCount',
                    'hints' => [
                        '*' => ['id', 'order_number', 'type', 'content', 'unlocked'],
                    ],
                ]
            ]);

        $jsonData = $response->json('data');

        // Vérifier que la session_step correspond bien à celle créée
        $this->assertEquals($sessionStep->id, $jsonData['session_step']['id']);
        $this->assertEquals($sessionStep->extra_hints, $jsonData['session_step']['extra_hints']);

        // Vérifier le step
        $this->assertEquals($activeStep->id, $jsonData['step']['id']);
        $this->assertEquals($activeStep->order_number, $jsonData['step']['order_number']);

        // Vérifier le nombre total d'étapes
        $this->assertEquals($riddle->steps()->count(), $jsonData['stepsCount']);

        // Vérifier l'état "unlocked" des indices : le premier toujours déverrouillé, les autres en fonction de extra_hints
        foreach ($jsonData['hints'] as $hint) {
            if ($hint['order_number'] === 1) {
                $this->assertTrue($hint['unlocked']);
            } else {
                $this->assertEquals($hint['order_number'] <= $sessionStep->extra_hints + 1, $hint['unlocked']);
            }
        }
    }

    /**
     * Test que la route GET /game/{gameSession} retourne une erreur si l'utilisateur n'est pas autorisé.
     */
    public function test_getActiveSession_fails_if_user_not_owner()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $gameSession = GameSession::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        /** @var User $otherUser */
        $this->actingAs($otherUser);

        $response = $this->getJson(route('game.active-session', ['gameSession' => $gameSession->id]));

        $response->assertStatus(Response::HTTP_FORBIDDEN)
            ->assertJson(['message' => 'Utilisateur non autorisé.']);
    }

    /**
     * Test que la route GET /game/{gameSession} retourne une erreur si la session n'est pas active.
     */
    public function test_getActiveSession_fails_if_session_not_active()
    {
        $user = User::factory()->create();

        $gameSession = GameSession::factory()->create([
            'user_id' => $user->id,
            'status' => 'completed', // statut non actif
        ]);

        /** @var User $user */
        $this->actingAs($user);

        $response = $this->getJson(route('game.active-session', ['gameSession' => $gameSession->id]));

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJson(['message' => 'La partie est déjà terminée ou abandonnée.']);
    }

    /**
     * Test que la route GET /game/{gameSession} retourne une erreur si la session n'a pas d'étape active.
     */
    public function test_getActiveSession_fails_if_no_active_step()
    {
        $user = User::factory()->create();

        $gameSession = GameSession::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        // Pas d'étape active liée (latestActiveSessionStep null)
        
        /** @var User $user */
        $this->actingAs($user);

        $response = $this->getJson(route('game.active-session', ['gameSession' => $gameSession->id]));

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJson(['message' => 'La partie est déjà terminée ou abandonnée.']);
    }
}
