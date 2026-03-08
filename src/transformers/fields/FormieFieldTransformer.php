<?php

namespace samuelreichor\coPilot\transformers\fields;

use craft\base\FieldInterface;
use craft\elements\Entry;

/**
 * Handles Verbb Formie form relation fields.
 *
 * The field value is an ElementQuery of Form elements (extends BaseRelationField).
 * Forms are read-only for the AI — it can see which forms are attached but not modify them.
 */
class FormieFieldTransformer implements FieldTransformerInterface
{
    public function getSupportedFieldClasses(): array
    {
        return [];
    }

    public function matchesField(FieldInterface $field): ?bool
    {
        $class = get_class($field);

        if ($class === 'verbb\formie\fields\Forms') {
            return true;
        }

        return null;
    }

    public function describeField(FieldInterface $field, array $fieldInfo): array
    {
        $fieldInfo['hint'] = 'Formie form relation (read-only). Array of form IDs.';

        return $fieldInfo;
    }

    public function serializeValue(FieldInterface $field, mixed $value, int $depth): mixed
    {
        if (!is_object($value) || !method_exists($value, 'all')) {
            return $value;
        }

        return array_map(function($form) {
            $data = [
                '_type' => 'formie_form',
                'id' => $form->id,
                'title' => $form->title,
            ];

            if (property_exists($form, 'handle') && $form->handle !== null) {
                $data['handle'] = $form->handle;
            }

            return $data;
        }, $value->all());
    }

    public function normalizeValue(FieldInterface $field, mixed $value, ?Entry $entry = null): mixed
    {
        return null;
    }
}
