<?php

namespace App\Providers;

use App\Repositories\GameSessionRepository;
use App\Repositories\HintRepository;
use App\Repositories\ReviewRepository;
use App\Repositories\RiddleRepository;
use App\Repositories\ScoreRepository;
use App\Repositories\SessionStepRepository;
use App\Repositories\StepRepository;
use App\Repositories\Interfaces\GameSessionRepositoryInterface;
use App\Repositories\Interfaces\HintRepositoryInterface;
use App\Repositories\Interfaces\ReviewRepositoryInterface;
use App\Repositories\Interfaces\RiddleRepositoryInterface;
use App\Repositories\Interfaces\ScoreRepositoryInterface;
use App\Repositories\Interfaces\SessionStepRepositoryInterface;
use App\Repositories\Interfaces\StepRepositoryInterface;
use App\Services\GameplayService;
use App\Services\GameSessionService;
use App\Services\HintService;
use App\Services\ReviewService;
use App\Services\RiddleService;
use App\Services\ScoreService;
use App\Services\StepService;
use App\Services\Interfaces\GameplayServiceInterface;
use App\Services\Interfaces\GameSessionServiceInterface;
use App\Services\Interfaces\HintServiceInterface;
use App\Services\Interfaces\ReviewServiceInterface;
use App\Services\Interfaces\RiddleServiceInterface;
use App\Services\Interfaces\ScoreServiceInterface;
use App\Services\Interfaces\StepServiceInterface;
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

        $this->app->bind(GameSessionRepositoryInterface::class, GameSessionRepository::class);
        $this->app->bind(HintRepositoryInterface::class, HintRepository::class);
        $this->app->bind(ReviewRepositoryInterface::class, ReviewRepository::class);
        $this->app->bind(RiddleRepositoryInterface::class, RiddleRepository::class);
        $this->app->bind(ScoreRepositoryInterface::class, ScoreRepository::class);
        $this->app->bind(SessionStepRepositoryInterface::class, SessionStepRepository::class);
        $this->app->bind(StepRepositoryInterface::class, StepRepository::class);

    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
