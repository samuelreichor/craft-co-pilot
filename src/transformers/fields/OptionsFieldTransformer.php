<?php

namespace samuelreichor\coPilot\transformers\fields;

use craft\base\FieldInterface;
use craft\elements\Entry;
use craft\fields\BaseOptionsField;
use craft\fields\Checkboxes;
use craft\fields\data\MultiOptionsFieldData;
use craft\fields\data\SingleOptionFieldData;
use craft\fields\MultiSelect;

/**
 * Handles option field types: Dropdown, RadioButtons, Checkboxes, MultiSelect.
 */
class OptionsFieldTransformer implements FieldTransformerInterface
{
    public function getSupportedFieldClasses(): array
    {
        return [
            BaseOptionsField::class,
        ];
    }

    public function matchesField(FieldInterface $field): ?bool
    {
        return null;
    }

    public function describeField(FieldInterface $field, array $fieldInfo): array
    {
        if ($field instanceof BaseOptionsField) {
            $fieldInfo['options'] = array_values(array_filter(
                array_map(fn($opt) => isset($opt['optgroup']) ? null : [
                    'label' => $opt['label'],
                    'value' => $opt['value'],
                ], $field->options),
            ));

            if ($field instanceof Checkboxes || $field instanceof MultiSelect) {
                $fieldInfo['valueFormat'] = 'array of option value strings';
                $fieldInfo['multiValue'] = true;
            } else {
                $fieldInfo['valueFormat'] = 'single option value string';
            }
        }

        return $fieldInfo;
    }

    public function serializeValue(FieldInterface $field, mixed $value, int $depth): mixed
    {
        if ($value instanceof SingleOptionFieldData) {
            return ['value' => (string) $value, 'label' => $value->label];
        }

        if ($value instanceof MultiOptionsFieldData) {
            return array_map(fn($opt) => ['value' => $opt->value, 'label' => $opt->label], (array) $value);
        }

        return $value;
    }

    public function normalizeValue(FieldInterface $field, mixed $value, ?Entry $entry = null): mixed
    {
        if (!$field instanceof BaseOptionsField) {
            return null;
        }

        // Multi-value: array of option objects → array of value strings
        if (($field instanceof Checkboxes || $field instanceof MultiSelect) && is_array($value)) {
            return array_map(fn($item) => is_array($item) && isset($item['value']) ? $item['value'] : $item, $value);
        }

        // Single-value: option object → value string
        if (is_array($value) && isset($value['value'])) {
            return $value['value'];
        }

        return null;
    }
}
