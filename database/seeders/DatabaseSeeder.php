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
    public function run(): void
    {
        // Créer les utilisateurs spécifiques
        $sylvain = User::factory()->create([
            'name' => 'Sylvain',
            'email' => 'a@a',
            'password' => Hash::make('a'),
            'email_verified_at' => now(),
        ]);

        $jean = User::factory()->create([
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
                                       ->where('status', 'published')
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
                    'user_id' => $user->id,
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
        $statuses = ['draft', 'published', 'published', 'published', 'disabled']; // Plus de chances d'être publié
        return $statuses[array_rand($statuses)];
    }

    /**
     * Obtenir un statut pour une session de jeu
     */
    private function getSessionStatus(bool $hasActiveSession): string
    {
        if ($hasActiveSession) {
            $statuses = ['completed', 'completed', 'abandoned'];
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
        $stepsCount = $steps->count();
        
        // Si la session est complétée, toutes les étapes sont complétées
        if ($sessionStatus === 'completed') {
            foreach ($steps as $index => $step) {
                $startTime = $now->copy()->subHours($stepsCount - $index)->subMinutes(rand(0, 59));
                $duration = rand(5, 30); // Durée en minutes
                $endTime = $startTime->copy()->addMinutes($duration);
                
                SessionStep::create([
                    'game_session_id' => $gameSession->id,
                    'step_id' => $step->id,
                    'extra_hints' => rand(0, $step->hints->count()),
                    'status' => 'completed',
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                ]);
            }
        } 
        // Si la session est abandonnée, les premières étapes sont complétées, puis une est abandonnée
        elseif ($sessionStatus === 'abandoned') {
            $abandonedStepIndex = rand(0, $stepsCount - 1);
            
            foreach ($steps as $index => $step) {
                $startTime = $now->copy()->subHours($stepsCount - $index)->subMinutes(rand(0, 59));
                $endTime = null;
                $stepStatus = '';
                
                if ($index < $abandonedStepIndex) {
                    $stepStatus = 'completed';
                    $duration = rand(5, 30); // Durée en minutes
                    $endTime = $startTime->copy()->addMinutes($duration);
                } elseif ($index === $abandonedStepIndex) {
                    $stepStatus = 'abandoned';
                    $duration = rand(5, 30); // Durée en minutes
                    $endTime = $startTime->copy()->addMinutes($duration);
                } else {
                    // Les étapes après celle abandonnée n'ont pas été commencées
                    continue; // Ne pas créer d'entrée pour ces étapes
                }
                
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
        // Si la session est active, les premières étapes sont complétées, puis une est active
        else {
            $activeStepIndex = rand(0, $stepsCount - 1);
            
            foreach ($steps as $index => $step) {
                $startTime = $now->copy()->subHours($stepsCount - $index)->subMinutes(rand(0, 59));
                $endTime = null;
                $stepStatus = '';
                
                if ($index < $activeStepIndex) {
                    $stepStatus = 'completed';
                    $duration = rand(5, 30); // Durée en minutes
                    $endTime = $startTime->copy()->addMinutes($duration);
                } elseif ($index === $activeStepIndex) {
                    $stepStatus = 'active';
                    // Pas de end_time pour une étape active
                } else {
                    // Les étapes après celle active n'ont pas été commencées
                    continue; // Ne pas créer d'entrée pour ces étapes
                }
                
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
    }

    // /**
    //  * Créer les étapes de session pour une session de jeu
    //  */
    // private function createSessionSteps($gameSession, $steps, $sessionStatus): void
    // {
    //     $now = Carbon::now();
    //     $hasActiveStep = false;
        
    //     foreach ($steps as $index => $step) {
    //         // Déterminer le statut de l'étape de session
    //         $stepStatus = $this->getStepStatus($sessionStatus, $index, $steps->count(), $hasActiveStep);
            
    //         // Si c'est une étape active, marquer qu'on a déjà une étape active
    //         if ($stepStatus === 'active') {
    //             $hasActiveStep = true;
    //         }
            
    //         // Calculer les temps de début et de fin
    //         $startTime = $now->copy()->subHours(rand(1, 24))->subMinutes(rand(0, 59));
    //         $endTime = null;
            
    //         if ($stepStatus === 'completed' || $stepStatus === 'abandoned') {
    //             $duration = rand(5, 30); // Durée en minutes
    //             $endTime = $startTime->copy()->addMinutes($duration);
    //         }
            
    //         // Créer l'étape de session
    //         SessionStep::create([
    //             'game_session_id' => $gameSession->id,
    //             'step_id' => $step->id,
    //             'extra_hints' => rand(0, $step->hints->count()),
    //             'status' => $stepStatus,
    //             'start_time' => $startTime,
    //             'end_time' => $endTime,
    //         ]);
    //     }
    // }

    // /**
    //  * Obtenir un statut pour une étape de session
    //  */
    // private function getStepStatus(string $sessionStatus, int $stepIndex, int $totalSteps, bool $hasActiveStep): string
    // {
    //     if ($sessionStatus === 'completed') {
    //         return 'completed';
    //     } elseif ($sessionStatus === 'abandoned') {
    //         // Si la session est abandonnée, les premières étapes sont complétées, puis une est abandonnée
    //         $abandonedStepIndex = rand(0, $totalSteps - 1);
    //         if ($stepIndex < $abandonedStepIndex) {
    //             return 'completed';
    //         } elseif ($stepIndex === $abandonedStepIndex) {
    //             return 'abandoned';
    //         } else {
    //             return 'active'; // Les étapes suivantes n'ont pas été commencées
    //         }
    //     } else { // Session active
    //         // Si la session est active, les premières étapes sont complétées, puis une est active
    //         if ($hasActiveStep) {
    //             return 'active'; // Les étapes suivantes n'ont pas été commencées
    //         } else {
    //             if ($stepIndex < $totalSteps - 1) {
    //                 return rand(0, 1) ? 'completed' : 'active';
    //             } else {
    //                 return 'active';
    //             }
    //         }
    //     }
    // }

    /**
     * Créer des avis pour les énigmes complétées
     */
    private function createReviews($users, $riddles): void
    {
        // Pour chaque utilisateur
        foreach ($users as $user) {
            // Récupérer les sessions de jeu complétées de l'utilisateur
            $completedSessions = GameSession::where('user_id', $user->id)
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
        foreach ($users as $user) {
            $weekScore = rand(0, 100);
            GlobalScore::factory()->create([
                'user_id' => $user->id,
                'period'  => 'week',
                'score'   => $weekScore,
            ]);
            $monthScore = rand($weekScore, 500);
            GlobalScore::factory()->create([
                'user_id' => $user->id,
                'period'  => 'month',
                'score'   => $monthScore,
            ]);
            $allScore = rand($monthScore, 2000);
            GlobalScore::factory()->create([
                'user_id' => $user->id,
                'period'  => 'all',
                'score'   => $allScore,
            ]);
        }
    }

}
