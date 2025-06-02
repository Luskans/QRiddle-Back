<?php
namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Riddle;
use App\Models\Step;
use App\Models\GameSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;

class PlayRiddleControllerTest extends TestCase
{
    use RefreshDatabase;


    /**
     * Test que la route POST /riddles/{riddle}/play démarre une session de jeu et retourne un JSON correct.
     */
    public function test_playRiddle_starts_game_and_returns_json()
    {
        $user = User::factory()->create();
        $riddle = Riddle::factory()->create(['is_private' => false]);
        Step::factory()->create([
            'riddle_id' => $riddle->id,
            'order_number' => 1,
        ]);

        /** @var User $user */
        $this->actingAs($user);

        $response = $this->postJson(route('riddles.play', ['riddle' => $riddle->id]));

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['id', 'riddle_id', 'user_id', 'status']])
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.riddle_id', $riddle->id)
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('game_sessions', [
            'user_id' => $user->id,
            'riddle_id' => $riddle->id,
            'status' => 'active',
        ]);
    }

    /**
     * Test que si une session active existe déjà, la route POST /riddles/{riddle}/play retourne cette session.
     */
    public function test_playRiddle_returns_existing_active_session()
    {
        $user = User::factory()->create();
        $riddle = Riddle::factory()->create(['is_private' => false]);
        Step::factory()->create([
            'riddle_id' => $riddle->id,
            'order_number' => 1,
        ]);
        $existingSession = GameSession::factory()->create([
            'user_id' => $user->id,
            'riddle_id' => $riddle->id,
            'status' => 'active',
        ]);

        /** @var User $user */
        $this->actingAs($user);

        $response = $this->postJson(route('riddles.play', ['riddle' => $riddle->id]));

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $existingSession->id);
    }

    /**
     * Test que la route POST /riddles/{riddle}/play exige un mot de passe valide pour les énigmes privées.
     */
    public function test_playRiddle_requires_valid_password_for_private_riddle()
    {
        $user = User::factory()->create();
        $riddle = Riddle::factory()->create([
            'is_private' => true,
            'password' => 'secret123',
        ]);
        Step::factory()->create([
            'riddle_id' => $riddle->id,
            'order_number' => 1,
        ]);

        /** @var User $user */
        $this->actingAs($user);

        // Sans mot de passe : erreur validation
        $response = $this->postJson(route('riddles.play', ['riddle' => $riddle->id]));
        $response->assertStatus(500);

        // Mot de passe incorrect
        $response = $this->postJson(route('riddles.play', ['riddle' => $riddle->id]), [
            'password' => 'wrongpass',
        ]);
        $response->assertStatus(Response::HTTP_FORBIDDEN)
            ->assertJsonFragment(['message' => 'Mot de passe incorrect.']);

        // Mot de passe correct
        $response = $this->postJson(route('riddles.play', ['riddle' => $riddle->id]), [
            'password' => 'secret123',
        ]);
        $response->assertStatus(200)
            ->assertJsonPath('data.user_id', $user->id);
    }

    /**
     * Test que la route POST /riddles/{riddle}/play retourne une erreur si l'énigme ne contient aucune étape.
     */
    public function test_playRiddle_fails_if_no_steps()
    {
        $user = User::factory()->create();
        $riddle = Riddle::factory()->create(['is_private' => false]);

        /** @var User $user */
        $this->actingAs($user);

        $response = $this->postJson(route('riddles.play', ['riddle' => $riddle->id]));

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonFragment(['message' => 'Cette énigme ne contient aucune étape.']);
    }
}
