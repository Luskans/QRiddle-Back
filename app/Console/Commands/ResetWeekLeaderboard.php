<?php

namespace App\Console\Commands;

use App\Models\GlobalScore;
use Illuminate\Console\Command;

class ResetWeekLeaderboard extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'week-leaderboard:reset';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset the week leaderboard every Sunday at midnght.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $count = GlobalScore::where('period', 'week')->delete();
        $this->info("✅ Week leader board reset, $count global scores deleted.");
    }
}
