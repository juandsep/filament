<?php

namespace Vibefilter\Filament\Tests;

use Vibefilter\Filament\VibefilterPlugin;

class ServiceProviderTest extends TestCase
{
    public function test_config_is_merged(): void
    {
        $this->assertSame('typesafe', config('vibefilter.driver'));
        $this->assertSame(0.8, config('vibefilter.threshold'));
    }

    public function test_plugin_has_id(): void
    {
        $this->assertSame('vibefilter', VibefilterPlugin::make()->getId());
    }

    public function test_migration_creates_tables(): void
    {
        $this->assertTrue($this->app['db']->connection()->getSchemaBuilder()->hasTable('vibefilter_decisions'));
    }
}
