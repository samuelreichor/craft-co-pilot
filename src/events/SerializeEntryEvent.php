<?php

namespace samuelreichor\coPilot\events;

use craft\elements\Entry;
use yii\base\Event;

/**
 * Fired before an entry is serialized for AI context.
 * Allows modifying or excluding fields before serialization.
 */
class SerializeEntryEvent extends Event
{
    public Entry $entry;

    /** @var string[] Field handles to include */
    public array $fields;

    /** Set to true to cancel serialization */
    public bool $cancel = false;
}
