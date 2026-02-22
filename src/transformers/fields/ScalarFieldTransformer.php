<?php

namespace samuelreichor\coPilot\transformers\fields;

use craft\base\FieldInterface;
use craft\elements\Entry;
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
 * Handles scalar field types: PlainText, Number, Range, Lightswitch, Date, Time, Color, Email, Url, Icon, Country.
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
        ];
    }

    public function matchesField(FieldInterface $field): ?bool
    {
        return null;
    }

    public function describeField(FieldInterface $field, array $fieldInfo): array
    {
        if ($field instanceof PlainTextField) {
            $fieldInfo['valueFormat'] = 'string';
            if ($field->multiline) {
                $fieldInfo['multiLine'] = true;
            }
        } elseif ($field instanceof NumberField) {
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
        } elseif ($field instanceof RangeField) {
            $fieldInfo['valueFormat'] = 'number';
            $fieldInfo['min'] = $field->min;
            $fieldInfo['max'] = $field->max;
            $fieldInfo['step'] = $field->step;
            $fieldInfo['hint'] = "Integer between {$field->min} and {$field->max}.";
        } elseif ($field instanceof LightswitchField) {
            $fieldInfo['valueFormat'] = 'boolean';
        } elseif ($field instanceof DateField) {
            $fieldInfo['valueFormat'] = 'string';
            if ($field->showDate && $field->showTime) {
                $fieldInfo['hint'] = 'ISO 8601 datetime, e.g. "2024-06-15T14:30:00".';
            } elseif ($field->showTime) {
                $fieldInfo['hint'] = 'ISO 8601 time, e.g. "14:30:00".';
            } else {
                $fieldInfo['hint'] = 'ISO 8601 date, e.g. "2024-06-15".';
            }
        } elseif ($field instanceof TimeField) {
            $fieldInfo['valueFormat'] = 'string';
            $fieldInfo['hint'] = 'Time string, e.g. "14:30:00" or "09:00".';
        } elseif ($field instanceof ColorField) {
            $fieldInfo['valueFormat'] = 'string';
            if (!$field->allowCustomColors && !empty($field->palette)) {
                $colors = array_map(fn($entry) => $entry['color'], $field->palette);
                $fieldInfo['palette'] = $colors;
                $fieldInfo['hint'] = 'Pick one of the palette colors.';
            }
        } elseif ($field instanceof EmailField) {
            $fieldInfo['valueFormat'] = 'string';
            $fieldInfo['hint'] = 'Valid email address.';
        } elseif ($field instanceof UrlField) {
            $fieldInfo['valueFormat'] = 'string';
            $fieldInfo['hint'] = 'Full URL including protocol.';
        } elseif ($field instanceof IconField) {
            $fieldInfo['valueFormat'] = 'string';
            $fieldInfo['hint'] = 'Font Awesome name, e.g. "house", "user".';
        } elseif ($field instanceof CountryField) {
            $fieldInfo['valueFormat'] = 'string';
            $fieldInfo['hint'] = 'Two-letter country code, e.g. "US", "DE".';
        }

        return $fieldInfo;
    }

    public function serializeValue(FieldInterface $field, mixed $value, int $depth): mixed
    {
        if ($value instanceof ColorData) {
            return $value->getHex();
        }

        if ($value instanceof IconData) {
            return $value->name;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('c');
        }

        return $value;
    }

    public function normalizeValue(FieldInterface $field, mixed $value, ?Entry $entry = null): mixed
    {
        // Country: AI may send {"value": "US"}, {"countryCode": "DE"}, {"name": "..."} or []
        if ($field instanceof CountryField) {
            if (is_array($value)) {
                if (isset($value['value'])) {
                    return $value['value'];
                }
                if (isset($value['countryCode'])) {
                    return $value['countryCode'];
                }
                if ($value === []) {
                    return null;
                }
            }
        }

        // Time: AI may send full ISO datetime instead of time-only string
        if ($field instanceof TimeField && is_string($value) && str_contains($value, 'T')) {
            $parsed = date_create($value);
            if ($parsed !== false) {
                return $parsed->format('H:i:s');
            }
        }

        return null;
    }
}
