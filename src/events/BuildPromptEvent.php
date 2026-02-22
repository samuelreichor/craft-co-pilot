<?php

namespace samuelreichor\coPilot\events;

use yii\base\Event;

/**
 * Fired when building the system prompt.
 * Allows adding custom prompt sections.
 */
class BuildPromptEvent extends Event
{
    /** @var string[] Prompt sections to be joined */
    public array $sections = [];
}
