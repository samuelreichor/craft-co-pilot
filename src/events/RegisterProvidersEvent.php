<?php

namespace samuelreichor\coPilot\events;

use samuelreichor\coPilot\providers\ProviderInterface;
use yii\base\Event;

/**
 * Fired when registering available AI providers.
 * Allows adding custom providers.
 */
class RegisterProvidersEvent extends Event
{
    /** @var array<string, ProviderInterface> */
    public array $providers = [];
}
