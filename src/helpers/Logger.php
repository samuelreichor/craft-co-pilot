<?php

namespace samuelreichor\coPilot\helpers;

use Craft;
use samuelreichor\coPilot\CoPilot;

/**
 * Centralized logging helper.
 *
 * - Info(): only logs when debug=true in plugin settings
 * - warning()/error(): always log
 *
 * All messages are logged under the 'co-pilot' category → co-pilot.log.
 */
final class Logger
{
    public static function info(string $message): void
    {
        if (!CoPilot::getInstance()->getSettings()->debug) {
            return;
        }

        Craft::info($message, 'co-pilot');
    }

    public static function warning(string $message): void
    {
        Craft::warning($message, 'co-pilot');
    }

    public static function error(string $message): void
    {
        Craft::error($message, 'co-pilot');
    }
}
