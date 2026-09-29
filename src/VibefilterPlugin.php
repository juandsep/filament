<?php

namespace Vibefilter\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;

class VibefilterPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'vibefilter';
    }

    public function register(Panel $panel): void
    {
        //
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
