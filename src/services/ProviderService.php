<?php

namespace samuelreichor\coPilot\services;

use craft\base\Component;
use samuelreichor\coPilot\CoPilot;
use samuelreichor\coPilot\events\RegisterProvidersEvent;
use samuelreichor\coPilot\providers\AnthropicProvider;
use samuelreichor\coPilot\providers\GeminiProvider;
use samuelreichor\coPilot\providers\OpenAIProvider;
use samuelreichor\coPilot\providers\ProviderInterface;

/**
 * Manages AI provider registration and selection.
 */
class ProviderService extends Component
{
    public const EVENT_REGISTER_PROVIDERS = 'registerProviders';

    /** @var ProviderInterface[]|null */
    private ?array $providers = null;

    /**
     * Returns the currently active provider based on plugin settings.
     */
    public function getActiveProvider(): ProviderInterface
    {
        $settings = CoPilot::getInstance()->getSettings();
        $providers = $this->getProviders();

        return $providers[$settings->activeProvider]
            ?? throw new \RuntimeException("Provider '{$settings->activeProvider}' not found.");
    }

    /**
     * Returns a specific provider by handle.
     */
    public function getProvider(string $handle): ?ProviderInterface
    {
        return $this->getProviders()[$handle] ?? null;
    }

    /**
     * Returns all registered providers keyed by handle.
     *
     * @return array<string, ProviderInterface>
     */
    public function getProviders(): array
    {
        if ($this->providers !== null) {
            return $this->providers;
        }

        $event = new RegisterProvidersEvent();
        $event->providers = [
            'openai' => new OpenAIProvider(),
            'anthropic' => new AnthropicProvider(),
            'gemini' => new GeminiProvider(),
        ];

        $this->trigger(self::EVENT_REGISTER_PROVIDERS, $event);

        $this->providers = $event->providers;

        return $this->providers;
    }
}
