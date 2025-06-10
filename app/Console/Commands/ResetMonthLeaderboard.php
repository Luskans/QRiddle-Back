<?php

namespace App\Console\Commands;

use App\Models\GlobalScore;
use Illuminate\Console\Command;

class ResetMonthLeaderboard extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'month-leaderboard:reset';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset the month leaderboard the first day of every month at midnght.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $count = GlobalScore::where('period', 'month')->delete();
        $this->info("✅ Month leader board reset, $count global scores deleted.");
    }
}
