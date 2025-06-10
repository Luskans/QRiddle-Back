<?php

use Illuminate\Support\Facades\Schedule;


Schedule::command('sessions:abandon')->dailyAt('00:00');

Schedule::command('week-leaderboard:reset')->weekly();

Schedule::command('month-leaderboard:reset')->monthly();
