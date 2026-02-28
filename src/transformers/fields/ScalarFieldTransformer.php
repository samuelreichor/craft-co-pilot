<?php

namespace samuelreichor\coPilot\transformers\fields;

use craft\base\FieldInterface;
use craft\elements\Entry;
use craft\fields\BaseOptionsField;
use craft\fields\Color as ColorField;
use craft\fields\Country as CountryField;
use craft\fields\data\ColorData;
use craft\fields\data\IconData;
use craft\fields\Date as DateField;
use craft\fields\Email as EmailField;
use craft\fields\Icon as IconField;
use craft\fields\Lightswitch as LightswitchField;
use craft\fields\Number as NumberField;
use craft\fields\PlainText as PlainTextField;
use craft\fields\Range as RangeField;
use craft\fields\Time as TimeField;
use craft\fields\Url as UrlField;

/**
 * Handles scalar field types: PlainText, Number, Range, Lightswitch, Date, Time, Color, Email, Url, Icon, Country,
 * and option fields: Dropdown, RadioButtons, ButtonGroup, Checkboxes, MultiSelect.
 */
class ScalarFieldTransformer implements FieldTransformerInterface
{
    public function getSupportedFieldClasses(): array
    {
        return [
            PlainTextField::class,
            NumberField::class,
            LightswitchField::class,
            DateField::class,
            ColorField::class,
            EmailField::class,
            RangeField::class,
            TimeField::class,
            UrlField::class,
            IconField::class,
            CountryField::class,
            BaseOptionsField::class,
        ];
    }

    public function matchesField(FieldInterface $field): ?bool
    {
        return null;
    }

    public function describeField(FieldInterface $field, array $fieldInfo): array
    {
        return match (true) {
            $field instanceof PlainTextField => $this->describePlainText($field, $fieldInfo),
            $field instanceof NumberField => $this->describeNumber($field, $fieldInfo),
            $field instanceof RangeField => $this->describeRange($field, $fieldInfo),
            $field instanceof LightswitchField => $this->describeScalar($fieldInfo, 'boolean'),
            $field instanceof DateField => $this->describeDate($field, $fieldInfo),
            $field instanceof TimeField => $this->describeScalar($fieldInfo, 'string', 'Time string, e.g. "14:30:00" or "09:00".'),
            $field instanceof ColorField => $this->describeColor($field, $fieldInfo),
            $field instanceof EmailField => $this->describeScalar($fieldInfo, 'string', 'Valid email address.'),
            $field instanceof UrlField => $this->describeScalar($fieldInfo, 'string', 'Full URL including protocol.'),
            $field instanceof IconField => $this->describeScalar($fieldInfo, 'string', 'Font Awesome name, e.g. "house", "user".'),
            $field instanceof CountryField => $this->describeScalar($fieldInfo, 'string', 'Two-letter country code, e.g. "US", "DE".'),
            $field instanceof BaseOptionsField => $this->describeOptions($field, $fieldInfo),
            default => $fieldInfo,
        };
    }

    public function serializeValue(FieldInterface $field, mixed $value, int $depth): mixed
    {
        return match (true) {
            $value instanceof ColorData => $value->getHex(),
            $value instanceof IconData => $value->name,
            $value instanceof \DateTimeInterface => $value->format('c'),
            default => $value,
        };
    }

    public function normalizeValue(FieldInterface $field, mixed $value, ?Entry $entry = null): mixed
    {
        return match (true) {
            $field instanceof CountryField && is_array($value) => $this->normalizeCountryValue($value),
            $field instanceof TimeField && is_string($value) && str_contains($value, 'T') => $this->normalizeTimeValue($value),
            $field instanceof BaseOptionsField && is_array($value) => $this->normalizeOptionsValue($value),
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $fieldInfo
     * @return array<string, mixed>
     */
    private function describeScalar(array $fieldInfo, string $format, ?string $hint = null): array
    {
        $fieldInfo['valueFormat'] = $format;
        if ($hint !== null) {
            $fieldInfo['hint'] = $hint;
        }

        return $fieldInfo;
    }

    /**
     * @param array<string, mixed> $fieldInfo
     * @return array<string, mixed>
     */
    private function describePlainText(PlainTextField $field, array $fieldInfo): array
    {
        $fieldInfo['valueFormat'] = 'string';
        if ($field->multiline) {
            $fieldInfo['multiLine'] = true;
        }

        return $fieldInfo;
    }

    /**
     * @param array<string, mixed> $fieldInfo
     * @return array<string, mixed>
     */
    private function describeNumber(NumberField $field, array $fieldInfo): array
    {
        $fieldInfo['valueFormat'] = 'number';
        if ($field->min !== null) {
            $fieldInfo['min'] = $field->min;
        }
        if ($field->max !== null) {
            $fieldInfo['max'] = $field->max;
        }
        if ($field->decimals !== null && $field->decimals > 0) {
            $fieldInfo['decimals'] = $field->decimals;
        }

        return $fieldInfo;
    }

    /**
     * @param array<string, mixed> $fieldInfo
     * @return array<string, mixed>
     */
    private function describeRange(RangeField $field, array $fieldInfo): array
    {
        $fieldInfo['valueFormat'] = 'number';
        $fieldInfo['min'] = $field->min;
        $fieldInfo['max'] = $field->max;
        $fieldInfo['step'] = $field->step;
        $fieldInfo['hint'] = "Integer between {$field->min} and {$field->max}.";

        return $fieldInfo;
    }

    /**
     * @param array<string, mixed> $fieldInfo
     * @return array<string, mixed>
     */
    private function describeDate(DateField $field, array $fieldInfo): array
    {
        $fieldInfo['valueFormat'] = 'string';
        $fieldInfo['hint'] = match (true) {
            $field->showDate && $field->showTime => 'ISO 8601 datetime, e.g. "2024-06-15T14:30:00".',
            $field->showTime => 'ISO 8601 time, e.g. "14:30:00".',
            default => 'ISO 8601 date, e.g. "2024-06-15".',
        };

        return $fieldInfo;
    }

    /**
     * @param array<string, mixed> $fieldInfo
     * @return array<string, mixed>
     */
    private function describeColor(ColorField $field, array $fieldInfo): array
    {
        $fieldInfo['valueFormat'] = 'string';
        if (!$field->allowCustomColors && !empty($field->palette)) {
            $colors = array_map(fn($entry) => $entry['color'], $field->palette);
            $fieldInfo['palette'] = $colors;
            $fieldInfo['hint'] = 'Pick one of the palette colors.';
        }

        return $fieldInfo;
    }

    /**
     * @param array<string, mixed> $fieldInfo
     * @return array<string, mixed>
     */
    private function describeOptions(BaseOptionsField $field, array $fieldInfo): array
    {
        $options = [];
        foreach ($field->options as $opt) {
            if (isset($opt['value']) && $opt['value'] !== '') {
                $options[] = $opt['value'];
            }
        }
        $fieldInfo['options'] = $options;
        $isMulti = $field instanceof \craft\fields\Checkboxes || $field instanceof \craft\fields\MultiSelect;
        if ($isMulti) {
            $fieldInfo['valueFormat'] = 'array of option values';
            $fieldInfo['hint'] = 'Send an array of values, e.g. ["' . implode('", "', array_slice($options, 0, 2)) . '"].';
        } else {
            $fieldInfo['valueFormat'] = 'string';
            $fieldInfo['hint'] = 'Send one of the option values as a plain string.';
        }

        return $fieldInfo;
    }

    /**
     * @param array<string, mixed> $value
     */
    private function normalizeCountryValue(array $value): mixed
    {
        if (isset($value['value'])) {
            return $value['value'];
        }

        if (isset($value['countryCode'])) {
            return $value['countryCode'];
        }

        return null;
    }

    private function normalizeTimeValue(string $value): mixed
    {
        $parsed = date_create($value);
        if ($parsed !== false) {
            return $parsed->format('H:i:s');
        }

        return null;
    }

    /**
     * @param array<int|string, mixed> $value
     */
    private function normalizeOptionsValue(array $value): mixed
    {
        // Single object: {"value": "optionA", "label": "Option A"} → "optionA"
        if (isset($value['value'])) {
            return $value['value'];
        }

        // Array of objects: [{"value": "optionA"}, ...] → ["optionA", ...]
        if (array_is_list($value)) {
            return array_map(
                fn($item) => is_array($item) && isset($item['value']) ? $item['value'] : $item,
                $value,
            );
        }

        // Boolean map: {"optionA": true, "optionB": false} → ["optionA"]
        if ($this->isBooleanMap($value)) {
            return array_keys(array_filter($value));
        }

        return null;
    }

    /**
     * Checks if an array is a boolean map (all values are booleans).
     *
     * @param array<string, mixed> $value
     */
    private function isBooleanMap(array $value): bool
    {
        foreach ($value as $v) {
            if (!is_bool($v)) {
                return false;
            }
        }

        return true;
    }
}
