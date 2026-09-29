<?php

namespace Vibefilter\Filament;

use Illuminate\Support\Manager;
use Vibefilter\Filament\Contracts\DecisionDriver;
use Vibefilter\Filament\Drivers\FakeDriver;
use Vibefilter\Filament\Drivers\TypeSafeDriver;

/**
 * @mixin DecisionDriver
 */
class DecisionManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return $this->config->get('vibefilter.driver', 'typesafe');
    }

    protected function createTypesafeDriver(): DecisionDriver
    {
        $config = $this->config->get('vibefilter.drivers.typesafe', []);

        return new TypeSafeDriver(
            apiKey: $config['api_key'] ?? null,
            baseUrl: $config['base_url'] ?? 'https://api.typesafe.ai/v1',
            model: $config['model'] ?? 'jev-latest',
            timeout: $config['timeout'] ?? 60,
            batchSize: $this->config->get('vibefilter.batch_size', 100),
            concurrency: $this->config->get('vibefilter.concurrency', 10),
            retryDelays: $config['retry_delays'] ?? [500, 2000, 5000],
        );
    }

    protected function createFakeDriver(): DecisionDriver
    {
        return new FakeDriver;
    }
}
