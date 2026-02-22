<?php

namespace samuelreichor\coPilot\services;

use Craft;
use craft\base\Component;
use craft\base\FieldInterface;
use craft\elements\Entry;
use samuelreichor\coPilot\CoPilot;

/**
 * Normalizes AI-provided field values into formats Craft expects.
 * Delegates to field transformers via the TransformerRegistry.
 */
class FieldNormalizer extends Component
{
    /**
     * The raw field handle as provided by the AI. Available during normalizeValue()
     * so transformers can use the layout handle (which may differ from the field's original handle)
     * for operations like Matrix block merging.
     */
    private ?string $currentFieldHandle = null;

    /**
     * Normalizes a field value based on the field type.
     * For new entries (no existing entry), pass null as $entry.
     */
    public function normalize(string $fieldHandle, mixed $value, ?Entry $entry = null): mixed
    {
        // Strip _type serialization markers that AI models may echo back
        $value = $this->stripSerializationMarkers($value);

        $field = $this->resolveField($fieldHandle, $entry);

        if ($field === null) {
            return $value;
        }

        $transformer = CoPilot::getInstance()->transformerRegistry->getTransformerForField($field);

        if ($transformer === null) {
            return $value;
        }

        $this->currentFieldHandle = $fieldHandle;
        $normalized = $transformer->normalizeValue($field, $value, $entry);
        $this->currentFieldHandle = null;

        // null means no normalization needed — return the original value
        return $normalized ?? $value;
    }

    /**
     * Returns the raw AI-provided field handle during normalization.
     * Useful for transformers that need the layout handle (e.g. Matrix block merging).
     */
    public function getCurrentFieldHandle(): ?string
    {
        return $this->currentFieldHandle;
    }

    /**
     * Recursively strips serialization markers (e.g. "_type") from field values.
     * These markers are added during serializeValue() for AI context but must never
     * be written back to Craft. Weak models sometimes echo them back verbatim.
     */
    private function stripSerializationMarkers(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        unset($value['_type']);

        foreach ($value as $key => $item) {
            $value[$key] = $this->stripSerializationMarkers($item);
        }

        return $value;
    }

    /**
     * Resolves a field by handle, checking the entry's field layout first (supports custom layout handles),
     * then falling back to the global fields service.
     */
    public function resolveField(string $fieldHandle, ?Entry $entry): ?FieldInterface
    {
        $field = $entry?->getFieldLayout()?->getFieldByHandle($fieldHandle);

        if ($field !== null) {
            return $field;
        }

        return Craft::$app->getFields()->getFieldByHandle($fieldHandle);
    }
}
