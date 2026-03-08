<?php

namespace samuelreichor\coPilot\transformers;

/**
 * Provides a fallback serializer for field values without a dedicated transformer.
 */
trait SerializeFallbackTrait
{
    private function serializeFallback(mixed $value): mixed
    {
        if (is_object($value)) {
            if ($value instanceof \DateTimeInterface) {
                return $value->format('c');
            }

            if (method_exists($value, '__toString')) {
                return (string) $value;
            }

            return null;
        }

        if (is_array($value)) {
            return array_map(static function($item) {
                if (is_object($item)) {
                    return method_exists($item, '__toString') ? (string) $item : null;
                }

                return $item;
            }, $value);
        }

        return $value;
    }
}
