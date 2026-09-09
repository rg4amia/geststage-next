<?php

namespace App\Providers;

use App\Domain\Payment\Services\Prime\PrimeCalculatorService;
use App\Domain\Payment\Services\Prime\PrimeConfigurationService;
use App\Domain\Payment\Services\Prime\Strategies\PrimeMirahStrategy;
use App\Domain\Payment\Services\Prime\Strategies\PrimeQualificationStrategy;
use App\Domain\Payment\Services\Prime\Strategies\PrimeSmigStrategy;
use App\Domain\Payment\Services\Prime\Strategies\PrimeStageEcoleStrategy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerPrimeEngine();
    }

    /**
     * Moteur de calcul des primes.
     *
     * Le barème est résolu une fois par requête (singleton) : les stratégies le
     * consultent à chaque calcul et une liste de paiements en interroge des
     * milliers.
     */
    protected function registerPrimeEngine(): void
    {
        $this->app->singleton(PrimeConfigurationService::class);

        $this->app->singleton(PrimeCalculatorService::class, fn ($app): PrimeCalculatorService => new PrimeCalculatorService([
            $app->make(PrimeMirahStrategy::class),
            $app->make(PrimeQualificationStrategy::class),
            $app->make(PrimeStageEcoleStrategy::class),
            $app->make(PrimeSmigStrategy::class),
        ]));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        Gate::before(function ($user, $ability) {
            return $user->hasRole('administrateur') ? true : null;
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
