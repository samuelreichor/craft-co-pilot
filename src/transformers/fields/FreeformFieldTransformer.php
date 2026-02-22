<?php

namespace samuelreichor\coPilot\transformers\fields;

use craft\base\FieldInterface;
use craft\elements\Entry;

/**
 * Handles Solspace Freeform form fields.
 *
 * The field value is a single Form object (stores an integer form ID).
 * Forms are read-only for the AI — it can see which form is attached but not modify it.
 */
class FreeformFieldTransformer implements FieldTransformerInterface
{
    public function getSupportedFieldClasses(): array
    {
        return [];
    }

    public function matchesField(FieldInterface $field): ?bool
    {
        $class = get_class($field);

        if ($class === 'Solspace\Freeform\FieldTypes\FormFieldType') {
            return true;
        }

        return null;
    }

    public function describeField(FieldInterface $field, array $fieldInfo): array
    {
        $fieldInfo['hint'] = 'Freeform form reference (read-only). Single form ID.';

        return $fieldInfo;
    }

    public function serializeValue(FieldInterface $field, mixed $value, int $depth): mixed
    {
        if ($value === null) {
            return null;
        }

        // Single Form object
        if (is_object($value)) {
            $data = ['_type' => 'freeform_form'];

            if (method_exists($value, 'getId')) {
                $data['id'] = $value->getId();
            }

            if (method_exists($value, 'getName')) {
                $data['name'] = $value->getName();
            }

            if (method_exists($value, 'getHandle')) {
                $data['handle'] = $value->getHandle();
            }

            return $data;
        }

        // Raw integer ID
        if (is_int($value)) {
            return ['_type' => 'freeform_form', 'id' => $value];
        }

        return $value;
    }

    public function normalizeValue(FieldInterface $field, mixed $value, ?Entry $entry = null): mixed
    {
        return null;
    }
}
