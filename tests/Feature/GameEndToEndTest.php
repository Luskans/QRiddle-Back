<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Riddle;
use App\Models\Step;
use App\Models\SessionStep;
use App\Models\GameSession;
use App\Models\Hint;
use App\Services\Interfaces\ScoreServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class GameEndToEndTest extends TestCase
{
    use RefreshDatabase;

    public function test_end_to_end_full_game_with_hint_and_error()
    {
        $user = User::factory()->create();
        /** @var User $user */
        $this->actingAs($user);

        // Créer une énigme avec deux étapes et des indices
        $riddle = Riddle::factory()->create(['is_private' => false]);

        $step1 = Step::factory()->create(['riddle_id' => $riddle->id, 'order_number' => 1, 'qr_code' => 'qr-step-1']);
        $step2 = Step::factory()->create(['riddle_id' => $riddle->id, 'order_number' => 2, 'qr_code' => 'qr-step-2']);

        Hint::factory()->count(2)->create(['step_id' => $step2->id]);

        // 1. Démarrer la session
        $response = $this->postJson(route('riddles.play', ['riddle' => $riddle->id]));
        $response->assertStatus(200);
        $gameSessionId = $response->json('data.id');

        // 2. Récupérer la session active
        $response = $this->getJson(route('game.active-session', ['gameSession' => $gameSessionId]));
        $response->assertStatus(200);
        $this->assertEquals($step1->id, $response->json('data.step.id'));

        // 3. Valider la 1ère étape avec bon QR code
        $response = $this->postJson(route('game.validate-step', ['gameSession' => $gameSessionId]), [
            'qr_code' => 'qr-step-1',
        ]);
        $response->assertStatus(200);
        $this->assertFalse($response->json('data.game_completed'));

        // 4. Valider la 2ème étape avec un QR code invalide
        $response = $this->postJson(route('game.validate-step', ['gameSession' => $gameSessionId]), [
            'qr_code' => 'wrong-code',
        ]);
        $response->assertStatus(422); // erreur attendue

        // 5. Déverrouiller un indice
        $response = $this->postJson(route('game.unlock-hint', ['gameSession' => $gameSessionId]), [
            'hint_order_number' => 2,
        ]);
        $response->assertStatus(200);
        $this->assertDatabaseHas('session_steps', [
            'game_session_id' => $gameSessionId,
            'step_id' => $step2->id,
            'extra_hints' => 1,
        ]);

        // 6. Valider la 2ème étape avec le bon QR code
        // Mock du ScoreService
        $scoreServiceMock = Mockery::mock(ScoreServiceInterface::class);
        $scoreServiceMock->shouldReceive('calculateFinalScore')->once()->andReturn(150);
        $scoreServiceMock->shouldReceive('updateGlobalScores')->once();
        $this->app->instance(ScoreServiceInterface::class, $scoreServiceMock);

        $response = $this->postJson(route('game.validate-step', ['gameSession' => $gameSessionId]), [
            'qr_code' => 'qr-step-2',
        ]);
        $response->assertStatus(200);
        $this->assertTrue($response->json('data.game_completed'));

        // 7. Récupérer la session complétée
        $response = $this->getJson(route('game.completed-session', ['gameSession' => $gameSessionId]));
        $response->assertStatus(200);
        $response->assertJsonFragment([
            'id' => $gameSessionId,
            'score' => 150,
            'riddle_id' => $riddle->id,
        ]);
    }
}
