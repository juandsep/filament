<?php

namespace Vibefilter\Filament;

use Filament\Support\Facades\FilamentView;
use Filament\Tables\View\TablesRenderHook;
use Illuminate\Support\HtmlString;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Vibefilter\Filament\Contracts\DecisionDriver;
use Vibefilter\Filament\Livewire\PrescoreVibeFilters;

class VibefilterServiceProvider extends PackageServiceProvider
{
    public static string $name = 'vibefilter';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(static::$name)
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_vibefilter_tables');
    }

    public function packageBooted(): void
    {
        // An empty slot above every table's filter indicators. The vibe filter
        // streams its progress bar into it while it scores rows.
        FilamentView::registerRenderHook(
            TablesRenderHook::TOOLBAR_AFTER,
            fn (): HtmlString => new HtmlString('<div data-vibefilter-progress></div>'),
        );
    }

    public function packageRegistered(): void
    {
        // Livewire wires up its component hooks once, when it boots, so this
        // has to be registered before that: here, not in packageBooted().
        Livewire::componentHook(PrescoreVibeFilters::class);

        // Scoped, not singleton: under Octane it's rebuilt for every request.
        $this->app->scoped(DecisionManager::class, fn ($app) => new DecisionManager($app));
        $this->app->bind(DecisionDriver::class, fn ($app) => $app->make(DecisionManager::class)->driver());
        $this->app->bind(Scorer::class, fn ($app) => new Scorer($app->make(DecisionDriver::class)));
    }
}
