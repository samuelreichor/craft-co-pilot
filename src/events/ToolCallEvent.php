<?php

namespace samuelreichor\coPilot\events;

use yii\base\Event;

/**
 * Fired before and after a tool call is executed.
 */
class ToolCallEvent extends Event
{
    public string $toolName;

    /** @var array<string, mixed> */
    public array $params;

    /** @var array<string, mixed>|null Available only in the after-event */
    public ?array $result = null;

    /** Set to true to cancel the tool call (before-event only) */
    public bool $cancel = false;
}
