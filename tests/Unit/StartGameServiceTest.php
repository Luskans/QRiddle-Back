<?php

namespace Tests\Unit;

use App\Models\GameSession;
use App\Models\Riddle;
use App\Models\SessionStep;
use App\Models\Step;
use App\Models\User;
use App\Services\GameplayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;
use Tests\Traits\GameTestHelper;

class StartGameServiceTest extends TestCase
{
    use RefreshDatabase, GameTestHelper;


    /**
     * Prépare une énigme avec au moins une étape en base.
     *
     * @return Riddle
     */
    protected function createRiddleWithSteps()
    {
        // Création d'une énigme publique avec un créateur
        $riddle = Riddle::factory()->create([
            'is_private' => false,
            'password'   => null,
        ]);

        // Crée une étape pour l'énigme
        Step::factory()->create([
            'riddle_id'    => $riddle->id,
            'order_number' => 1,
            // Vous pouvez définir d'autres valeurs (qr_code, latitude, longitude) si besoin
        ]);

        return $riddle;
    }

    /**
     * Vérifie que si une session active existe déjà, startGame la retourne.
     */
    public function test_startGame_returns_existing_active_session()
    {
        $user = User::factory()->create();
        $riddle = $this->createRiddleWithSteps();

        // Crée une session de jeu active pour ce riddle et cet utilisateur
        $existingSession = GameSession::factory()->create([
            'riddle_id' => $riddle->id,
            'user_id'   => $user->id,
            'status'    => 'active',
            'score'     => 0,
        ]);

        $service = new GameplayService();

        // Créer une requête vide (aucune donnée n'est requise pour un riddle public)
        $request = new Request();

        $resultSession = $service->startGame($riddle, $user, $request);

        $this->assertEquals($existingSession->id, $resultSession->id);
    }

    /**
     * Vérifie que pour un riddle public sans session active, une nouvelle session est créée.
     */
    public function test_startGame_creates_new_session_for_public_riddle()
    {
        $user = User::factory()->create();
        $riddle = $this->createRiddleWithSteps();

        // S'assurer qu'aucune session n'existe pour cet utilisateur et cette énigme
        $this->assertNull(
            GameSession::where('riddle_id', $riddle->id)
                ->where('user_id', $user->id)
                ->first()
        );

        $service = new GameplayService();
        $request = new Request();

        // Puisque la méthode utilise DB::transaction, nous pouvons autoriser sa bonne exécution
        $newSession = $service->startGame($riddle, $user, $request);

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
        $user = User::factory()->create();
        // Créer une énigme privée avec un mot de passe
        $riddle = Riddle::factory()->create([
            'is_private' => true,
            'password'   => 'secret123',
        ]);

        // Créer au moins une étape pour l'énigme
        Step::factory()->create([
            'riddle_id'    => $riddle->id,
            'order_number' => 1,
        ]);

        $service = new GameplayService();

        // Simuler une requête contenant un mauvais mot de passe
        $request = new Request([
            'password' => 'wrongpass'
        ]);

        $this->expectExceptionMessage('Mot de passe incorrect.');
        $this->expectExceptionCode(Response::HTTP_FORBIDDEN);

        $service->startGame($riddle, $user, $request);
    }

    /**
     * Vérifie qu'une exception est levée si l'énigme ne contient aucune étape.
     */
    public function test_startGame_throws_exception_if_no_steps_in_riddle()
    {
        $user = User::factory()->create();
        // Créer une énigme publique sans étapes
        $riddle = Riddle::factory()->create([
            'is_private' => false,
            'password'   => null,
        ]);

        $service = new GameplayService();
        $request = new Request();

        $this->expectExceptionMessage('Cette énigme ne contient aucune étape.');
        $this->expectExceptionCode(Response::HTTP_UNPROCESSABLE_ENTITY);

        $service->startGame($riddle, $user, $request);
    }

    /**
     * Vérifie qu'une ancienne session non active est supprimée et qu'une nouvelle session est créée.
     */
    public function test_startGame_deletes_non_active_existing_session_and_creates_new_one()
    {
        $user = User::factory()->create();
        $riddle = $this->createRiddleWithSteps();

        // Crée une ancienne session (par exemple abandonnée)
        $oldSession = GameSession::factory()->create([
            'riddle_id' => $riddle->id,
            'user_id'   => $user->id,
            'status'    => 'abandoned',
        ]);

        $this->assertDatabaseHas('game_sessions', ['id' => $oldSession->id]);

        $service = new GameplayService();
        $request = new Request();

        $newSession = $service->startGame($riddle, $user, $request);

        $this->assertNotEquals($oldSession->id, $newSession->id);
        $this->assertDatabaseMissing('game_sessions', ['id' => $oldSession->id]);
        $this->assertEquals('active', $newSession->status);
    }

    public function test_startGame_abandons_other_active_sessions_for_user()
    {
        $user = User::factory()->create();

        // Une autre session active pour un autre riddle
        $otherRiddle = $this->createRiddleWithSteps();
        $otherSession = GameSession::factory()->create([
            'riddle_id' => $otherRiddle->id,
            'user_id'   => $user->id,
            'status'    => 'active',
        ]);
        $step = SessionStep::factory()->create([
            'game_session_id' => $otherSession->id,
            'status' => 'active',
            'start_time' => now(),
        ]);

        $riddle = $this->createRiddleWithSteps();
        $service = new GameplayService();
        $request = new Request();

        $service->startGame($riddle, $user, $request);

        $this->assertDatabaseHas('game_sessions', [
            'id' => $otherSession->id,
            'status' => 'abandoned',
        ]);
        $this->assertDatabaseHas('session_steps', [
            'id' => $step->id,
            'status' => 'abandoned',
        ]);
    }
}