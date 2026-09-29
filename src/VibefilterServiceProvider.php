<?php

namespace Vibefilter\Filament;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Vibefilter\Filament\Contracts\DecisionDriver;

class VibefilterServiceProvider extends PackageServiceProvider
{
    public static string $name = 'vibefilter';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(static::$name)
            ->hasConfigFile()
            ->hasMigration('create_vibefilter_tables');
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(DecisionManager::class, fn ($app) => new DecisionManager($app));
        $this->app->bind(DecisionDriver::class, fn ($app) => $app->make(DecisionManager::class)->driver());
        $this->app->bind(Scorer::class, fn ($app) => new Scorer($app->make(DecisionDriver::class)));
    }
}
