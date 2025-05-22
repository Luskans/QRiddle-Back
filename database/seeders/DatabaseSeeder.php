<?php

namespace Database\Seeders;

use App\Models\GameScore;
use App\Models\GameSession;
use App\Models\GlobalScore;
use App\Models\Hint;
use App\Models\Review;
use App\Models\Riddle;
use App\Models\SessionStep;
use App\Models\Step;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    // public function run(): void
    // {
    //     // SYLVAIN with differents riddles
    //     $sylvain = User::factory()->create([
    //         'name' => 'Sylvain',
    //         'email'    => 'a@a',
    //         'password' => Hash::make('a'),
    //     ]);

    //     // Riddle public
    //     $riddle1 = Riddle::factory()->create([
    //         'creator_id' => $sylvain->id,
    //         'status' => 'active',
    //         'is_private' => false
    //     ]);
    //     // Riddle public Step 1
    //     $riddle1Step1 = Step::factory()->create([
    //         'riddle_id'    => $riddle1->id,
    //         'order_number' => 1,
    //     ]);
    //     // Riddle public Step 1 Hint Text
    //     Hint::factory()->create([
    //         'step_id'      => $riddle1Step1->id,
    //         'order_number' => 1,
    //         'type' => 'text',
    //     ]);
    //     // Riddle public Step 1 Hint Image
    //     Hint::factory()->create([
    //         'step_id'      => $riddle1Step1->id,
    //         'order_number' => 2,
    //         'type' => 'image',
    //         'content' => "/seeders/image/hint01.jpg"
    //     ]);
    //     // Riddle public Step 1 Hint Audio
    //     Hint::factory()->create([
    //         'step_id'      => $riddle1Step1->id,
    //         'order_number' => 3,
    //         'type' => 'audio',
    //         'content' => "/seeders/audio/hint01.mp3"
    //     ]);
    //     // Other steps
    //     for ($j = 1; $j < 3; $j++) {
    //         $step = Step::factory()->create([
    //             'riddle_id'    => $riddle1->id,
    //             'order_number' => $j + 1,
    //         ]);
    //         $hintCount = rand(1, 3);
    //         for ($k = 0; $k < $hintCount; $k++) {
    //             Hint::factory()->create([
    //                 'step_id'      => $step->id,
    //                 'order_number' => $k + 1,
    //                 'type' => 'text'
    //             ]);
    //         }
    //     }

    //     // Riddle private
    //     $riddle2 = Riddle::factory()->create([
    //         'creator_id' => $sylvain->id,
    //         'status' => 'active',
    //         'is_private' => true
    //     ]);
    //     for ($j = 0; $j < 5; $j++) {
    //         $step = Step::factory()->create([
    //             'riddle_id'    => $riddle2->id,
    //             'order_number' => $j + 1,
    //         ]);
    //         $hintCount = rand(1, 3);
    //         for ($k = 0; $k < $hintCount; $k++) {
    //             Hint::factory()->create([
    //                 'step_id'      => $step->id,
    //                 'order_number' => $k + 1,
    //                 'type' => 'text'
    //             ]);
    //         }
    //     }

    //     // Riddle draft
    //     $riddle3 = Riddle::factory()->create([
    //         'creator_id' => $sylvain->id,
    //         'status' => 'draft',
    //         'is_private' => true
    //     ]);
    //     $stepCount = rand(3, 6);
    //     for ($j = 0; $j < $stepCount; $j++) {
    //         $step = Step::factory()->create([
    //             'riddle_id'    => $riddle3->id,
    //             'order_number' => $j + 1,
    //         ]);
    //         $hintCount = rand(1, 3);
    //         for ($k = 0; $k < $hintCount; $k++) {
    //             Hint::factory()->create([
    //                 'step_id'      => $step->id,
    //                 'order_number' => $k + 1,
    //                 'type' => 'text'
    //             ]);
    //         }
    //     }

    //     // Riddle disabled
    //     $riddle4 = Riddle::factory()->create([
    //         'creator_id' => $sylvain->id,
    //         'status' => 'disabled',
    //         'is_private' => false
    //     ]);
    //     $stepCount = rand(3, 6);
    //     for ($j = 0; $j < $stepCount; $j++) {
    //         $step = Step::factory()->create([
    //             'riddle_id'    => $riddle4->id,
    //             'order_number' => $j + 1,
    //         ]);
    //         $hintCount = rand(1, 3);
    //         for ($k = 0; $k < $hintCount; $k++) {
    //             Hint::factory()->create([
    //                 'step_id'      => $step->id,
    //                 'order_number' => $k + 1,
    //                 'type' => 'text'
    //             ]);
    //         }
    //     }

    //     // JEAN with no riddles testing sylvain's riddles
    //     $jean = User::factory()->create([
    //         'name' => 'Jean',
    //         'email'    => 'b@b',
    //         'password' => Hash::make('b'),
    //     ]);

    //     // PIERRE with no score
    //     $pierre = User::factory()->create([
    //         'name' => 'Pierre',
    //         'email'    => 'c@c',
    //         'password' => Hash::make('c'),
    //         'email_verified_at' => null
    //     ]);

    //     // PAUL with unverified email
    //     $paul = User::factory()->create([
    //         'name' => 'Paul',
    //         'email'    => 'd@d',
    //         'password' => Hash::make('d'),
    //     ]);
        
    //     // Other random users
    //     $otherUsers = User::factory(50)->create();


    //     // Reviews on sylvain's riddle1
    //     Review::factory()->create([
    //         'riddle_id' => $riddle1->id,
    //         'user_id'   => $jean->id,
    //     ]);
    //     Review::factory()->create([
    //         'riddle_id' => $riddle1->id,
    //         'user_id'   => $pierre->id,
    //     ]);


    //     // Global scores for all except Pierre and Paul
    //     $allUsers = collect([$sylvain, $jean])->merge($otherUsers);
    //     foreach ($allUsers as $user) {
    //         $weekScore = rand(0, 100);
    //         GlobalScore::factory()->create([
    //             'user_id' => $user->id,
    //             'period'  => 'week',
    //             'score'   => $weekScore,
    //         ]);
    //         $monthScore = rand($weekScore, 500);
    //         GlobalScore::factory()->create([
    //             'user_id' => $user->id,
    //             'period'  => 'month',
    //             'score'   => $monthScore,
    //         ]);
    //         $allScore = rand($monthScore, 2000);
    //         GlobalScore::factory()->create([
    //             'user_id' => $user->id,
    //             'period'  => 'all',
    //             'score'   => $allScore,
    //         ]);
    //     }


    //     // GameSessions and SessionSteps completed for Jean
    //     $gameSession1 = GameSession::factory()->create([
    //         'riddle_id'  => $riddle1->id,
    //         'player_id'  => $jean->id,
    //         'status'     => 'completed'
    //     ]);
    //     for ($i = 1; $i <= $riddle1->steps()->count(); $i++) {
    //         SessionStep::factory()->create([
    //             'game_session_id'  => $gameSession1->id,
    //             'step_id'          => $i,
    //             'hint_used_number' => rand(0, 2),
    //             'status'           => 'completed',
    //             'start_time'       => Carbon::now()->subMinutes(30)->addMinutes($i * 5),
    //             'end_time'         => Carbon::now()->subMinutes(30)->addMinutes($i * 5 + 3),
    //         ]);
    //     }

    //     // GameSessions and SessionSteps active for Jean
    //     $gameSession2 = GameSession::factory()->create([
    //         'riddle_id'  => $riddle2->id,
    //         'player_id'  => $jean->id,
    //         'status'     => 'active'
    //     ]);
    //     for ($i = 1; $i < 4; $i++) {
    //         SessionStep::factory()->create([
    //             'game_session_id'  => $gameSession2->id,
    //             'step_id'          => $riddle1->steps()->count() + $i,
    //             'hint_used_number' => rand(0, 2),
    //             'status'           => 'completed',
    //             'start_time'       => Carbon::now()->subMinutes(30)->addMinutes($i * 5),
    //             'end_time'         => Carbon::now()->subMinutes(30)->addMinutes($i * 5 + 3),
    //         ]);
    //     }
    //     SessionStep::factory()->create([
    //         'game_session_id'  => $gameSession2->id,
    //         'step_id'          => $riddle1->steps()->count() + 3 + 1,
    //         'hint_used_number' => 1,
    //         'status'           => 'active',
    //         'start_time'       => Carbon::now()->subMinutes(30)->addMinutes($i * 5),
    //         'end_time'         => null
    //     ]);
    // }














    public function run(): void
    {
        // Créer les utilisateurs spécifiques
        $sylvain = User::create([
            'name' => 'Sylvain',
            'email' => 'a@a',
            'password' => Hash::make('a'),
            'email_verified_at' => now(),
        ]);

        $jean = User::create([
            'name' => 'Jean',
            'email' => 'b@b',
            'password' => Hash::make('b'),
            'email_verified_at' => now(),
        ]);

        // Créer les autres utilisateurs
        $users = User::factory(78)->create();
        $allUsers = collect([$sylvain, $jean])->merge($users);

        // Créer des énigmes pour chaque utilisateur
        $riddles = collect();
        foreach ($allUsers as $user) {
            // Chaque utilisateur crée entre 1 et 3 énigmes
            $userRiddles = Riddle::factory(rand(1, 3))
                ->create([
                    'creator_id' => $user->id,
                    'status' => $this->getRandomStatus(),
                ]);
            
            $riddles = $riddles->merge($userRiddles);
        }

        // Créer des étapes pour chaque énigme
        foreach ($riddles as $riddle) {
            $stepCount = rand(3, 8); // Entre 3 et 8 étapes par énigme
            
            for ($i = 1; $i <= $stepCount; $i++) {
                $step = Step::factory()->create([
                    'riddle_id' => $riddle->id,
                    'order_number' => $i,
                ]);
                
                // Créer des indices pour chaque étape
                $hintCount = rand(1, 3); // Entre 1 et 3 indices par étape
                for ($j = 1; $j <= $hintCount; $j++) {
                    Hint::factory()->create([
                        'step_id' => $step->id,
                        'order_number' => $j,
                    ]);
                }
            }
        }

        // Créer des sessions de jeu
        // Chaque utilisateur joue à plusieurs énigmes, mais pas les siennes
        foreach ($allUsers as $user) {
            // Récupérer les énigmes que l'utilisateur n'a pas créées et qui sont actives
            $availableRiddles = $riddles->where('creator_id', '!=', $user->id)
                                       ->where('status', 'active')
                                       ->values();
            
            if ($availableRiddles->isEmpty()) {
                continue;
            }
            
            // Nombre de sessions de jeu par utilisateur (entre 1 et 5)
            $sessionCount = rand(1, 5);
            
            // S'assurer qu'il n'y a qu'une seule session active par utilisateur
            $hasActiveSession = false;
            
            for ($i = 0; $i < $sessionCount && $i < $availableRiddles->count(); $i++) {
                $riddle = $availableRiddles[$i];
                
                // Déterminer le statut de la session
                $status = $this->getSessionStatus($hasActiveSession);
                
                // Si c'est une session active, marquer qu'on a déjà une session active
                if ($status === 'active') {
                    $hasActiveSession = true;
                }
                
                // Créer la session de jeu
                $gameSession = GameSession::create([
                    'riddle_id' => $riddle->id,
                    'player_id' => $user->id,
                    'status' => $status,
                    'score' => $status === 'completed' ? rand(50, 100) : 0,
                ]);
                
                // Récupérer les étapes de l'énigme
                $steps = $riddle->steps()->orderBy('order_number')->get();
                
                // Créer les étapes de session
                $this->createSessionSteps($gameSession, $steps, $status);
            }
        }

        // Créer des avis pour les énigmes complétées
        $this->createReviews($allUsers, $riddles);

        // Créer des scores globaux
        $this->createGlobalScores($allUsers);
    }

    /**
     * Obtenir un statut aléatoire pour une énigme
     */
    private function getRandomStatus(): string
    {
        $statuses = ['draft', 'active', 'active', 'active', 'disabled']; // Plus de chances d'être active
        return $statuses[array_rand($statuses)];
    }

    /**
     * Obtenir un statut pour une session de jeu
     */
    private function getSessionStatus(bool $hasActiveSession): string
    {
        if ($hasActiveSession) {
            $statuses = ['completed', 'abandoned'];
            return $statuses[array_rand($statuses)];
        } else {
            $statuses = ['active', 'completed', 'completed', 'abandoned'];
            return $statuses[array_rand($statuses)];
        }
    }

    /**
     * Créer les étapes de session pour une session de jeu
     */
    private function createSessionSteps($gameSession, $steps, $sessionStatus): void
    {
        $now = Carbon::now();
        $hasActiveStep = false;
        
        foreach ($steps as $index => $step) {
            // Déterminer le statut de l'étape de session
            $stepStatus = $this->getStepStatus($sessionStatus, $index, $steps->count(), $hasActiveStep);
            
            // Si c'est une étape active, marquer qu'on a déjà une étape active
            if ($stepStatus === 'active') {
                $hasActiveStep = true;
            }
            
            // Calculer les temps de début et de fin
            $startTime = $now->copy()->subHours(rand(1, 24))->subMinutes(rand(0, 59));
            $endTime = null;
            
            if ($stepStatus === 'completed' || $stepStatus === 'abandoned') {
                $duration = rand(5, 30); // Durée en minutes
                $endTime = $startTime->copy()->addMinutes($duration);
            }
            
            // Créer l'étape de session
            SessionStep::create([
                'game_session_id' => $gameSession->id,
                'step_id' => $step->id,
                'extra_hints' => rand(0, $step->hints->count()),
                'status' => $stepStatus,
                'start_time' => $startTime,
                'end_time' => $endTime,
            ]);
        }
    }

    /**
     * Obtenir un statut pour une étape de session
     */
    private function getStepStatus(string $sessionStatus, int $stepIndex, int $totalSteps, bool $hasActiveStep): string
    {
        if ($sessionStatus === 'completed') {
            return 'completed';
        } elseif ($sessionStatus === 'abandoned') {
            // Si la session est abandonnée, les premières étapes sont complétées, puis une est abandonnée
            $abandonedStepIndex = rand(0, $totalSteps - 1);
            if ($stepIndex < $abandonedStepIndex) {
                return 'completed';
            } elseif ($stepIndex === $abandonedStepIndex) {
                return 'abandoned';
            } else {
                return 'active'; // Les étapes suivantes n'ont pas été commencées
            }
        } else { // Session active
            // Si la session est active, les premières étapes sont complétées, puis une est active
            if ($hasActiveStep) {
                return 'active'; // Les étapes suivantes n'ont pas été commencées
            } else {
                if ($stepIndex < $totalSteps - 1) {
                    return rand(0, 1) ? 'completed' : 'active';
                } else {
                    return 'active';
                }
            }
        }
    }

    /**
     * Créer des avis pour les énigmes complétées
     */
    private function createReviews($users, $riddles): void
    {
        // Pour chaque utilisateur
        foreach ($users as $user) {
            // Récupérer les sessions de jeu complétées de l'utilisateur
            $completedSessions = GameSession::where('player_id', $user->id)
                                          ->where('status', 'completed')
                                          ->get();
            
            // Pour chaque session complétée, créer un avis avec une probabilité de 70%
            foreach ($completedSessions as $session) {
                if (rand(1, 10) <= 7) { // 70% de chance
                    Review::create([
                        'riddle_id' => $session->riddle_id,
                        'user_id' => $user->id,
                        'content' => fake()->paragraph(rand(1, 3)),
                        'rating' => rand(1, 5),
                        'difficulty' => rand(1, 5),
                    ]);
                }
            }
        }
    }

    /**
     * Créer des scores globaux pour les utilisateurs
     */
    private function createGlobalScores($users): void
    {
        $periods = ['week', 'month', 'all'];
        
        foreach ($users as $user) {
            foreach ($periods as $period) {
                // Calculer un score basé sur les sessions complétées
                $completedSessions = GameSession::where('player_id', $user->id)
                                              ->where('status', 'completed')
                                              ->get();
                
                $score = $completedSessions->sum('score');
                
                // Ajouter un peu d'aléatoire pour différencier les périodes
                if ($period === 'week') {
                    $score = min($score, rand(0, 1000));
                } elseif ($period === 'month') {
                    $score = min($score, rand(1000, 5000));
                }
                
                GlobalScore::create([
                    'user_id' => $user->id,
                    'period' => $period,
                    'score' => $score,
                ]);
            }
        }
    }

}
