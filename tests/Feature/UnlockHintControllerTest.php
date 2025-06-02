<?php
namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\GameSession;
use App\Models\SessionStep;
use App\Models\Step;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;

class UnlockHintControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test que la route POST /game/{gameSession}/unlock-hint déverrouille un indice correctement.
     */
    public function test_unlockHint_successfully_unlocks_hint()
    {
        $user = User::factory()->create();
        $gameSession = GameSession::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $step = Step::factory()->create();
        $sessionStep = SessionStep::factory()->create([
            'game_session_id' => $gameSession->id,
            'step_id' => $step->id,
            'status' => 'active',
            'extra_hints' => 1,
        ]);

        /** @var User $user */
        $this->actingAs($user);

        $response = $this->postJson(route('game.unlock-hint', ['gameSession' => $gameSession->id]), [
            'hint_order_number' => 2, // débloquer indice numéro 2
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['data']);

        // Vérifier que extra_hints a été incrémenté
        $this->assertDatabaseHas('session_steps', [
            'id' => $sessionStep->id,
            'extra_hints' => 2,
        ]);
    }

    /**
     * Test que la route POST /game/{gameSession}/unlock-hint échoue si utilisateur non propriétaire.
     */
    public function test_unlockHint_fails_if_user_not_owner()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $gameSession = GameSession::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        /** @var User $otherUser */
        $this->actingAs($otherUser);

        $response = $this->postJson(route('game.unlock-hint', ['gameSession' => $gameSession->id]), [
            'hint_order_number' => 2,
        ]);

        $response->assertStatus(Response::HTTP_FORBIDDEN)
            ->assertJson(['message' => 'Utilisateur non autorisé.']);
    }

    /**
     * Test que la route POST /game/{gameSession}/unlock-hint échoue si l'étape est terminée ou abandonnée.
     */
    public function test_unlockHint_fails_if_no_active_step()
    {
        $user = User::factory()->create();

        $gameSession = GameSession::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        // Pas d'étape active liée (latestActiveSessionStep null)
        /** @var User $user */
        $this->actingAs($user);

        $response = $this->postJson(route('game.unlock-hint', ['gameSession' => $gameSession->id]), [
            'hint_order_number' => 2,
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJson(['message' => "L'étape est déjà terminée ou abandonnée."]);
    }

    /**
     * Test que la route POST /game/{gameSession}/unlock-hint échoue si l'indice est déjà déverrouillé.
     */
    public function test_unlockHint_fails_if_hint_already_unlocked()
    {
        $user = User::factory()->create();
        $gameSession = GameSession::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
        ]);
        $step = Step::factory()->create();
        $sessionStep = SessionStep::factory()->create([
            'game_session_id' => $gameSession->id,
            'step_id' => $step->id,
            'status' => 'active',
            'extra_hints' => 2,
        ]);

        /** @var User $user */
        $this->actingAs($user);

        // hint_order_number = 1 (toujours déverrouillé)
        $response1 = $this->postJson(route('game.unlock-hint', ['gameSession' => $gameSession->id]), [
            'hint_order_number' => 1,
        ]);
        $response1->assertStatus(Response::HTTP_BAD_REQUEST)
            ->assertJson(['message' => 'Indice déjà déverrouillé.']);

        // hint_order_number <= extra_hints
        $response2 = $this->postJson(route('game.unlock-hint', ['gameSession' => $gameSession->id]), [
            'hint_order_number' => 2,
        ]);
        $response2->assertStatus(Response::HTTP_BAD_REQUEST)
            ->assertJson(['message' => 'Indice déjà déverrouillé.']);
    }

    /**
     * Test que la route POST /game/{gameSession}/unlock-hint échoue si on saute un indice à débloquer.
     */
    public function test_unlockHint_fails_if_skipping_hint()
    {
        $user = User::factory()->create();
        $gameSession = GameSession::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
        ]);
        $step = Step::factory()->create();
        $sessionStep = SessionStep::factory()->create([
            'game_session_id' => $gameSession->id,
            'step_id' => $step->id,
            'status' => 'active',
            'extra_hints' => 1,
        ]);

        /** @var User $user */
        $this->actingAs($user);

        // On essaie de débloquer l'indice numéro 4 alors que 2 est le prochain possible
        $response = $this->postJson(route('game.unlock-hint', ['gameSession' => $gameSession->id]), [
            'hint_order_number' => 4,
        ]);

        $response->assertStatus(Response::HTTP_FORBIDDEN)
            ->assertJson(['message' => "Veuillez débloquer l'indice précédent."]);
    }

    /**
     * Test que la validation de la requête est bien appliquée.
     */
    public function test_unlockHint_validates_request()
    {
        $user = User::factory()->create();
        $gameSession = GameSession::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
        ]);
        /** @var User $user */
        $this->actingAs($user);

        // hint_order_number manquant
        $response = $this->postJson(route('game.unlock-hint', ['gameSession' => $gameSession->id]), []);
        $response->assertStatus(422); // Erreur de validation

        // hint_order_number non numérique
        $response2 = $this->postJson(route('game.unlock-hint', ['gameSession' => $gameSession->id]), [
            'hint_order_number' => 'abc',
        ]);
        $response2->assertStatus(422);
    }
}
