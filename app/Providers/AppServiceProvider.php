<?php

namespace App\Providers;

use App\Interfaces\GameServiceInterface;
use App\Interfaces\RiddleServiceInterface;
use App\Interfaces\ScoreServiceInterface;
use App\Services\GameService;
use App\Services\RiddleService;
use App\Services\ScoreService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(GameServiceInterface::class, GameService::class);
        $this->app->bind(RiddleServiceInterface::class, RiddleService::class);
        $this->app->bind(ScoreServiceInterface::class, ScoreService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
