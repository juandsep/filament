<?php

namespace Vibefilter\Filament;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

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
}
