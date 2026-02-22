<?php

namespace samuelreichor\coPilot\events;

use samuelreichor\coPilot\transformers\fields\FieldTransformerInterface;
use yii\base\Event;

/**
 * Fired when registering field transformers.
 * Allows adding custom field transformers for third-party field types.
 */
class RegisterFieldTransformersEvent extends Event
{
    /** @var FieldTransformerInterface[] */
    public array $transformers = [];
}
