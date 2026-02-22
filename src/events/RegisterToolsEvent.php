<?php

namespace samuelreichor\coPilot\events;

use samuelreichor\coPilot\tools\ToolInterface;
use yii\base\Event;

/**
 * Fired when registering available tools.
 * Allows adding custom tools.
 */
class RegisterToolsEvent extends Event
{
    /** @var ToolInterface[] */
    public array $tools = [];
}
