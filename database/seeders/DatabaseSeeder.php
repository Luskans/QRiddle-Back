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
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // SYLVAIN with differents riddles
        $sylvain = User::factory()->create([
            'name' => 'Sylvain',
            'email'    => 'a@a',
            'password' => Hash::make('a'),
        ]);

        // Riddle public
        $riddle1 = Riddle::factory()->create([
            'creator_id' => $sylvain->id,
            'status' => 'active',
            'is_private' => false
        ]);
        // Riddle public Step 1
        $riddle1Step1 = Step::factory()->create([
            'riddle_id'    => $riddle1->id,
            'order_number' => 1,
        ]);
        // Riddle public Step 1 Hint Text
        Hint::factory()->create([
            'step_id'      => $riddle1Step1->id,
            'order_number' => 1,
            'type' => 'text',
        ]);
        // Riddle public Step 1 Hint Image
        Hint::factory()->create([
            'step_id'      => $riddle1Step1->id,
            'order_number' => 2,
            'type' => 'image',
            'content' => "/seeders/image/hint01.jpg"
        ]);
        // Riddle public Step 1 Hint Audio
        Hint::factory()->create([
            'step_id'      => $riddle1Step1->id,
            'order_number' => 3,
            'type' => 'audio',
            'content' => "/seeders/audio/hint01.mp3"
        ]);
        // Other steps
        for ($j = 1; $j < 3; $j++) {
            $step = Step::factory()->create([
                'riddle_id'    => $riddle1->id,
                'order_number' => $j + 1,
            ]);
            $hintCount = rand(1, 3);
            for ($k = 0; $k < $hintCount; $k++) {
                Hint::factory()->create([
                    'step_id'      => $step->id,
                    'order_number' => $k + 1,
                    'type' => 'text'
                ]);
            }
        }

        // Riddle private
        $riddle2 = Riddle::factory()->create([
            'creator_id' => $sylvain->id,
            'status' => 'active',
            'is_private' => true
        ]);
        for ($j = 0; $j < 5; $j++) {
            $step = Step::factory()->create([
                'riddle_id'    => $riddle2->id,
                'order_number' => $j + 1,
            ]);
            $hintCount = rand(1, 3);
            for ($k = 0; $k < $hintCount; $k++) {
                Hint::factory()->create([
                    'step_id'      => $step->id,
                    'order_number' => $k + 1,
                    'type' => 'text'
                ]);
            }
        }

        // Riddle draft
        $riddle3 = Riddle::factory()->create([
            'creator_id' => $sylvain->id,
            'status' => 'draft',
            'is_private' => true
        ]);
        $stepCount = rand(3, 6);
        for ($j = 0; $j < $stepCount; $j++) {
            $step = Step::factory()->create([
                'riddle_id'    => $riddle3->id,
                'order_number' => $j + 1,
            ]);
            $hintCount = rand(1, 3);
            for ($k = 0; $k < $hintCount; $k++) {
                Hint::factory()->create([
                    'step_id'      => $step->id,
                    'order_number' => $k + 1,
                    'type' => 'text'
                ]);
            }
        }

        // Riddle disabled
        $riddle4 = Riddle::factory()->create([
            'creator_id' => $sylvain->id,
            'status' => 'disabled',
            'is_private' => false
        ]);
        $stepCount = rand(3, 6);
        for ($j = 0; $j < $stepCount; $j++) {
            $step = Step::factory()->create([
                'riddle_id'    => $riddle4->id,
                'order_number' => $j + 1,
            ]);
            $hintCount = rand(1, 3);
            for ($k = 0; $k < $hintCount; $k++) {
                Hint::factory()->create([
                    'step_id'      => $step->id,
                    'order_number' => $k + 1,
                    'type' => 'text'
                ]);
            }
        }

        // JEAN with no riddles testing sylvain's riddles
        $jean = User::factory()->create([
            'name' => 'Jean',
            'email'    => 'jean@email.com',
            'password' => Hash::make('jean'),
        ]);

        // PIERRE with no score
        $pierre = User::factory()->create([
            'name' => 'Pierre',
            'email'    => 'pierre@email.com',
            'password' => Hash::make('pierre'),
            'email_verified_at' => null
        ]);

        // PAUL with unverified email
        $paul = User::factory()->create([
            'name' => 'Paul',
            'email'    => 'paul@email.com',
            'password' => Hash::make('paul'),
        ]);
        
        // Other random users
        $otherUsers = User::factory(50)->create();


        // Reviews on sylvain's riddle1
        Review::factory()->create([
            'riddle_id' => $riddle1->id,
            'user_id'   => $jean->id,
        ]);
        Review::factory()->create([
            'riddle_id' => $riddle1->id,
            'user_id'   => $pierre->id,
        ]);


        // Global scores for all except Pierre and Paul
        $allUsers = collect([$sylvain, $jean])->merge($otherUsers);
        foreach ($allUsers as $user) {
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


        // GameSessions and SessionSteps completed for Jean
        $gameSession1 = GameSession::factory()->create([
            'riddle_id'  => $riddle1->id,
            'player_id'  => $jean->id,
            'status'     => 'completed'
        ]);
        for ($i = 1; $i <= $riddle1->steps()->count(); $i++) {
            SessionStep::factory()->create([
                'game_session_id'  => $gameSession1->id,
                'step_id'          => $i,
                'hint_used_number' => rand(0, 2),
                'status'           => 'completed',
                'start_time'       => Carbon::now()->subMinutes(30)->addMinutes($i * 5),
                'end_time'         => Carbon::now()->subMinutes(30)->addMinutes($i * 5 + 3),
            ]);
        }

        // GameSessions and SessionSteps active for Jean
        $gameSession2 = GameSession::factory()->create([
            'riddle_id'  => $riddle2->id,
            'player_id'  => $jean->id,
            'status'     => 'active'
        ]);
        for ($i = 1; $i < 4; $i++) {
            SessionStep::factory()->create([
                'game_session_id'  => $gameSession2->id,
                'step_id'          => $riddle1->steps()->count() + $i,
                'hint_used_number' => rand(0, 2),
                'status'           => 'completed',
                'start_time'       => Carbon::now()->subMinutes(30)->addMinutes($i * 5),
                'end_time'         => Carbon::now()->subMinutes(30)->addMinutes($i * 5 + 3),
            ]);
        }
        SessionStep::factory()->create([
            'game_session_id'  => $gameSession2->id,
            'step_id'          => $riddle1->steps()->count() + 3 + 1,
            'hint_used_number' => 1,
            'status'           => 'active',
            'start_time'       => Carbon::now()->subMinutes(30)->addMinutes($i * 5),
            'end_time'         => null
        ]);
    }
}
