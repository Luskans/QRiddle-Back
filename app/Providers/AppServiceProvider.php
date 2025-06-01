<?php

namespace App\Providers;

use App\Interfaces\GameplayServiceInterface;
use App\Interfaces\GameSessionServiceInterface;
use App\Interfaces\HintServiceInterface;
use App\Interfaces\ReviewServiceInterface;
use App\Interfaces\RiddleServiceInterface;
use App\Interfaces\ScoreServiceInterface;
use App\Interfaces\StepServiceInterface;
use App\Services\GameplayService;
use App\Services\GameSessionService;
use App\Services\HintService;
use App\Services\ReviewService;
use App\Services\RiddleService;
use App\Services\ScoreService;
use App\Services\StepService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(GameplayServiceInterface::class, GameplayService::class);
        $this->app->bind(GameSessionServiceInterface::class, GameSessionService::class);
        $this->app->bind(HintServiceInterface::class, HintService::class);
        $this->app->bind(ReviewServiceInterface::class, ReviewService::class);
        $this->app->bind(RiddleServiceInterface::class, RiddleService::class);
        $this->app->bind(ScoreServiceInterface::class, ScoreService::class);
        $this->app->bind(StepServiceInterface::class, StepService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
