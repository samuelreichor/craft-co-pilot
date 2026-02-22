<?php

namespace samuelreichor\coPilot\transformers\fields;

use craft\base\FieldInterface;
use craft\elements\Entry;

/**
 * Handles CKEditor rich text fields via class name matching.
 */
class RichTextFieldTransformer implements FieldTransformerInterface
{
    public function getSupportedFieldClasses(): array
    {
        return [];
    }

    public function matchesField(FieldInterface $field): ?bool
    {
        if (str_contains(get_class($field), 'ckeditor')) {
            return true;
        }

        return null;
    }

    public function describeField(FieldInterface $field, array $fieldInfo): array
    {
        $fieldInfo['valueFormat'] = 'HTML string';
        $fieldInfo['hint'] = 'Rich text as HTML. Use standard tags: <p>, <h2>-<h4>, <strong>, <em>, <a>, <ul>, <ol>, <li>, <blockquote>.';

        return $fieldInfo;
    }

    public function serializeValue(FieldInterface $field, mixed $value, int $depth): mixed
    {
        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        return $value;
    }

    public function normalizeValue(FieldInterface $field, mixed $value, ?Entry $entry = null): mixed
    {
        return null;
    }
}
