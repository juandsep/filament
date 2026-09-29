<?php

namespace Vibefilter\Filament\Drivers;

/**
 * TypeSafe Jev through OpenRouter's Decisions API, which takes the same
 * requests as TypeSafe's own API. Handy when you already have an OpenRouter
 * key. OpenRouter forwards to TypeSafe, so a Jev outage affects both.
 */
class OpenRouterDriver extends TypeSafeDriver
{
    /**
     * @param  array<int>  $retryDelays  Pauses in milliseconds before each retry.
     */
    public function __construct(
        ?string $apiKey,
        string $baseUrl = 'https://openrouter.ai/api/v1',
        string $model = 'typesafe/jev-1.13',
        int $timeout = 60,
        int $batchSize = 100,
        int $concurrency = 10,
        array $retryDelays = [500, 2000, 5000],
    ) {
        parent::__construct($apiKey, $baseUrl, $model, $timeout, $batchSize, $concurrency, $retryDelays);
    }

    public function name(): string
    {
        return 'openrouter';
    }

    protected function label(): string
    {
        return 'OpenRouter';
    }

    protected function missingKeyMessage(): string
    {
        return 'OpenRouter API key is missing. Set OPENROUTER_API_KEY in your .env file.';
    }
}
