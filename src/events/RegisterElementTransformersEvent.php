<?php

namespace samuelreichor\coPilot\events;

use samuelreichor\coPilot\transformers\elements\ElementTransformerInterface;
use yii\base\Event;

/**
 * Fired when registering element transformers.
 * Allows adding custom element transformers for third-party element types.
 */
class RegisterElementTransformersEvent extends Event
{
    /** @var ElementTransformerInterface[] */
    public array $transformers = [];
}
