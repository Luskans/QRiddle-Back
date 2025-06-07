<?php
namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\GameSession;
use App\Models\Riddle;
use App\Models\SessionStep;
use App\Models\Step;
use App\Services\Interfaces\ScoreServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Mockery;

class ValidateStepControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test que la route POST /game/{gameSession}/validate-step valide une étape correctement (avec étape suivante).
     */
    public function test_validateStep_validates_step_and_creates_next()
    {
        $user = User::factory()->create();
        $riddle = Riddle::factory()->create();
        
        // Créer 2 étapes dans la bonne ordre
        $step1 = Step::factory()->create(['riddle_id' => $riddle->id, 'order_number' => 1, 'qr_code' => 'code-123']);
        $step2 = Step::factory()->create(['riddle_id' => $riddle->id, 'order_number' => 2, 'qr_code' => 'code-456']);
        
        $gameSession = GameSession::factory()->create([
            'user_id' => $user->id,
            'riddle_id' => $riddle->id,
            'status' => 'active',
        ]);
        
        $sessionStep = SessionStep::factory()->create([
            'game_session_id' => $gameSession->id,
            'step_id' => $step1->id,
            'status' => 'active',
            'extra_hints' => 0,
        ]);
        
        /** @var User $user */
        $this->actingAs($user);
        
        $response = $this->postJson(route('game.validate-step', ['gameSession' => $gameSession->id]), [
            'qr_code' => 'code-123',
        ]);
        
        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['game_completed', 'game_session']]);
        
        // L'étape active doit maintenant être complétée
        $this->assertDatabaseHas('session_steps', [
            'id' => $sessionStep->id,
            'status' => 'completed',
        ]);
        
        // Une nouvelle session_step active pour l'étape suivante doit être créée
        $this->assertDatabaseHas('session_steps', [
            'game_session_id' => $gameSession->id,
            'step_id' => $step2->id,
            'status' => 'active',
        ]);
        
        // La session de jeu doit toujours être active (pas encore terminée)
        $this->assertDatabaseHas('game_sessions', [
            'id' => $gameSession->id,
            'status' => 'active',
        ]);
    }
    
    /**
     * Test que la route POST /game/{gameSession}/validate-step complète la session si dernière étape validée.
     */
    public function test_validateStep_completes_game_on_last_step()
    {
        $user = User::factory()->create();
        $riddle = Riddle::factory()->create();
        
        // Une seule étape pour cet énigme
        $step = Step::factory()->create(['riddle_id' => $riddle->id, 'order_number' => 1, 'qr_code' => 'last-code']);
        
        $gameSession = GameSession::factory()->create([
            'user_id' => $user->id,
            'riddle_id' => $riddle->id,
            'status' => 'active',
        ]);
        
        $sessionStep = SessionStep::factory()->create([
            'game_session_id' => $gameSession->id,
            'step_id' => $step->id,
            'status' => 'active',
            'extra_hints' => 0,
        ]);
        
        // Mocker le service de score pour contrôler le calcul final et la mise à jour
        $scoreServiceMock = Mockery::mock(ScoreServiceInterface::class);
        $scoreServiceMock->shouldReceive('calculateFinalScore')->once()->with(Mockery::on(fn ($arg) => $arg->id === $gameSession->id))->andReturn(123);
        $scoreServiceMock->shouldReceive('updateGlobalScores')->once()->with($user->id, 123);
        $this->app->instance(ScoreServiceInterface::class, $scoreServiceMock);
        
        /** @var User $user */
        $this->actingAs($user);
        
        $response = $this->postJson(route('game.validate-step', ['gameSession' => $gameSession->id]), [
            'qr_code' => 'last-code',
        ]);
        
        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['game_completed', 'game_session']]);
        
        $this->assertTrue($response['data']['game_completed']);
        
        // La session doit être marquée comme complétée avec le score final
        $this->assertDatabaseHas('game_sessions', [
            'id' => $gameSession->id,
            'status' => 'completed',
            'score' => 123,
        ]);
    }
    
    /**
     * Test que la validation échoue si l'utilisateur n'est pas propriétaire de la session.
     */
    public function test_validateStep_fails_if_user_not_owner()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $gameSession = GameSession::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
        ]);
        
        /** @var User $otherUser */
        $this->actingAs($otherUser);
        
        $response = $this->postJson(route('game.validate-step', ['gameSession' => $gameSession->id]), [
            'qr_code' => 'anything',
        ]);
        
        $response->assertStatus(Response::HTTP_FORBIDDEN)
            ->assertJson(['message' => 'Utilisateur non autorisé.']);
    }
    
    /**
     * Test que la validation échoue si le QR code ne correspond pas ou étape manquante.
     */
    public function test_validateStep_fails_if_invalid_qr_code_or_no_active_step()
    {
        $user = User::factory()->create();
        $riddle = Riddle::factory()->create();
        
        $step = Step::factory()->create(['riddle_id' => $riddle->id, 'order_number' => 1, 'qr_code' => 'correct-code']);
        
        $gameSession = GameSession::factory()->create([
            'user_id' => $user->id,
            'riddle_id' => $riddle->id,
            'status' => 'active',
        ]);
        
        // Pas d'étape active
        /** @var User $user */
        $this->actingAs($user);
        $response1 = $this->postJson(route('game.validate-step', ['gameSession' => $gameSession->id]), [
            'qr_code' => 'correct-code',
        ]);
        $response1->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJson(['message' => 'QR code non valide.']);
        
        // Ajouter une étape active mais QR code incorrect
        $sessionStep = SessionStep::factory()->create([
            'game_session_id' => $gameSession->id,
            'step_id' => $step->id,
            'status' => 'active',
            'extra_hints' => 0,
        ]);
        $response2 = $this->postJson(route('game.validate-step', ['gameSession' => $gameSession->id]), [
            'qr_code' => 'wrong-code',
        ]);
        $response2->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJson(['message' => 'QR code non valide.']);
    }
    
    /**
     * Test que la requête est bien validée (qr_code obligatoire et chaîne).
     */
    public function test_validateStep_validates_request()
    {
        $user = User::factory()->create();
        $gameSession = GameSession::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
        ]);
        
        /** @var User $user */
        $this->actingAs($user);
        
        // qr_code manquant
        $response1 = $this->postJson(route('game.validate-step', ['gameSession' => $gameSession->id]), []);
        $response1->assertStatus(422);
        
        // qr_code non-string
        $response2 = $this->postJson(route('game.validate-step', ['gameSession' => $gameSession->id]), [
            'qr_code' => ['not-a-string'],
        ]);
        $response2->assertStatus(422);
    }
}
