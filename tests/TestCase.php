<?php

namespace Vibefilter\Filament\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Vibefilter\Filament\VibefilterServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            VibefilterServiceProvider::class,
        ];
    }

    protected function defineDatabaseMigrations(): void
    {
        (include __DIR__.'/../database/migrations/create_vibefilter_tables.php.stub')->up();
    }
}
