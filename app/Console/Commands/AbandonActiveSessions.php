<?php

namespace App\Console\Commands;

use App\Models\GameSession;
use App\Models\SessionStep;
use Illuminate\Console\Command;

class AbandonActiveSessions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sessions:abandon';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Abandon all active game_sessions and session_steps every day at midnight.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $gameCount = GameSession::where('status', 'active')
            ->update(['status' => 'abandoned']);

        $stepCount = SessionStep::where('status', 'active')
            ->update(['status' => 'abandoned']);

        $this->info("✅ $gameCount game_sessions & $stepCount step_sessions abandonnés.");
    }
}
