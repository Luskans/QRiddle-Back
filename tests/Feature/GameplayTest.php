<?php

// namespace Tests\Feature;

// use App\Models\User;
// use App\Models\Riddle;
// use App\Models\Step;
// use App\Models\Hint;
// use App\Models\GameSession;
// use Illuminate\Foundation\Testing\RefreshDatabase;
// use Laravel\Sanctum\Sanctum;
// use Tests\TestCase;

// class GameplayTest extends TestCase
// {
//     use RefreshDatabase;

//     protected User $user;
//     protected Riddle $riddle;

//     protected function setUp(): void
//     {
//         parent::setUp();

//         $this->user = User::factory()->create();
//         Sanctum::actingAs($this->user);

//         $this->riddle = Riddle::factory()
//             ->has(Step::factory()->count(3)->has(Hint::factory()->count(2)))
//             ->create(['creator_id' => $this->user->id]);
//     }

//     public function test_user_can_start_a_game()
//     {
//         $response = $this->postJson("/api/riddles/{$this->riddle->id}/play");

//         $response->assertOk()
//             ->assertJsonStructure(['data' => ['id', 'riddle_id', 'user_id']]);

//         $this->assertDatabaseHas('game_sessions', [
//             'riddle_id' => $this->riddle->id,
//             'user_id' => $this->user->id,
//         ]);
//     }

//     public function test_user_can_validate_a_step()
//     {
//         $session = $this->startGameSession();

//         $currentStep = $this->riddle->steps()->orderBy('order')->first();

//         $response = $this->postJson("/api/game/{$session->id}/validate-step", [
//             'qr_code' => $currentStep->qr_code,
//         ]);

//         $response->assertOk()
//             ->assertJsonStructure(['data' => ['current_step_id', 'steps_completed']]);
//     }

//     public function test_user_can_unlock_hint()
//     {
//         $session = $this->startGameSession();

//         $response = $this->postJson("/api/game/{$session->id}/unlock-hint", [
//             'hint_order_number' => 1,
//         ]);

//         $response->assertOk()
//             ->assertJsonStructure(['data' => ['unlocked_hints']]);
//     }

//     public function test_user_can_abandon_game()
//     {
//         $session = $this->startGameSession();

//         $response = $this->patchJson("/api/game/{$session->id}");

//         $response->assertOk()
//             ->assertJsonPath('data.status', 'abandoned');
//     }

//     public function test_user_can_retrieve_active_session()
//     {
//         $session = $this->startGameSession();

//         $response = $this->getJson("/api/game/{$session->id}");

//         $response->assertOk()
//             ->assertJsonStructure(['data' => ['id', 'riddle_id', 'current_step_id']]);
//     }

//     public function test_user_can_retrieve_completed_session()
//     {
//         $session = $this->startGameSession();
//         $session->update(['status' => 'completed']);

//         $response = $this->getJson("/api/game/{$session->id}/complete");

//         $response->assertOk()
//             ->assertJsonStructure(['data' => ['id', 'status']])
//             ->assertJsonPath('data.status', 'completed');
//     }

//         public function test_cannot_start_game_with_invalid_riddle_id()
//     {
//         $invalidId = 9999;
//         $response = $this->postJson("/api/riddles/{$invalidId}/play");

//         $response->assertNotFound();
//     }

//     public function test_cannot_validate_step_with_wrong_qr_code()
//     {
//         $session = $this->startGameSession();

//         $response = $this->postJson("/api/game/{$session->id}/validate-step", [
//             'qr_code' => 'invalid-qr-code',
//         ]);

//         $response->assertStatus(400)
//                  ->assertJsonFragment(['message' => true]);
//     }

//     public function test_cannot_unlock_hint_with_invalid_order_number()
//     {
//         $session = $this->startGameSession();

//         $response = $this->postJson("/api/game/{$session->id}/unlock-hint", [
//             'hint_order_number' => 99,
//         ]);

//         $response->assertStatus(400)
//                  ->assertJsonFragment(['message' => true]);
//     }

//     public function test_user_cannot_access_game_session_of_another_user()
//     {
//         $session = $this->startGameSession();

//         Sanctum::actingAs(User::factory()->create());

//         $response = $this->getJson("/api/game/{$session->id}");

//         $response->assertStatus(403);
//     }

//     public function test_user_cannot_abandon_completed_game()
//     {
//         $session = $this->startGameSession();
//         $session->update(['status' => 'completed']);

//         $response = $this->patchJson("/api/game/{$session->id}");

//         $response->assertStatus(400)
//                  ->assertJsonFragment(['message' => true]);
//     }

//     public function test_validation_fails_without_qr_code()
//     {
//         $session = $this->startGameSession();

//         $response = $this->postJson("/api/game/{$session->id}/validate-step", []);

//         $response->assertStatus(422);
//         $response->assertJsonValidationErrors(['qr_code']);
//     }

//     public function test_unlock_hint_fails_without_hint_order_number()
//     {
//         $session = $this->startGameSession();

//         $response = $this->postJson("/api/game/{$session->id}/unlock-hint", []);

//         $response->assertStatus(422);
//         $response->assertJsonValidationErrors(['hint_order_number']);
//     }

//     protected function startGameSession(): GameSession
//     {
//         $response = $this->postJson("/api/riddles/{$this->riddle->id}/play");

//         $response->assertOk();

//         return GameSession::where('user_id', $this->user->id)
//             ->where('riddle_id', $this->riddle->id)
//             ->latest()->first();
//     }
// }
