<?php

namespace samuelreichor\coPilot\transformers;

use craft\base\ElementInterface;
use samuelreichor\coPilot\CoPilot;

/**
 * Provides shared serialization helpers for element transformers.
 */
trait SerializeFallbackTrait
{
    /**
     * Serializes all custom fields from an element's field layout.
     *
     * @param string[]|null $fieldHandles Optional filter — only serialize these handles
     * @return array<string, mixed>
     */
    private function serializeCustomFields(ElementInterface $element, int $depth, ?array $fieldHandles = null): array
    {
        $fieldLayout = $element->getFieldLayout();
        if (!$fieldLayout) {
            return [];
        }

        $registry = CoPilot::getInstance()->transformerRegistry;
        $fields = [];

        foreach ($registry->resolveFieldLayoutFields($fieldLayout) as $resolved) {
            $handle = $resolved['handle'];
            $field = $resolved['field'];

            if ($fieldHandles !== null && !in_array($handle, $fieldHandles, true)) {
                continue;
            }

            $value = $element->getFieldValue($handle);
            $transformer = $registry->getTransformerForField($field);

            if ($transformer !== null) {
                $fields[$handle] = $transformer->serializeValue($field, $value, $depth);
            } else {
                $fields[$handle] = $this->serializeFallback($value);
            }
        }

        return $fields;
    }

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
