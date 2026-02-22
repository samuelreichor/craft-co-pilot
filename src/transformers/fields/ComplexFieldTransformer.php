<?php

namespace samuelreichor\coPilot\transformers\fields;

use craft\base\FieldInterface;
use craft\elements\ContentBlock as ContentBlockElement;
use craft\elements\Entry;
use craft\fields\ContentBlock as ContentBlockField;
use craft\fields\data\JsonData;
use craft\fields\data\LinkData;
use craft\fields\Json as JsonField;
use craft\fields\Link as LinkField;
use craft\fields\Matrix as MatrixField;
use craft\fields\Money as MoneyField;
use craft\fields\Table as TableField;
use samuelreichor\coPilot\CoPilot;

/**
 * Handles complex field types: Matrix, ContentBlock, Link, Money, JSON, Table.
 */
class ComplexFieldTransformer implements FieldTransformerInterface
{
    public function getSupportedFieldClasses(): array
    {
        return [
            MatrixField::class,
            ContentBlockField::class,
            LinkField::class,
            MoneyField::class,
            JsonField::class,
            TableField::class,
        ];
    }

    public function matchesField(FieldInterface $field): ?bool
    {
        return null;
    }

    public function describeField(FieldInterface $field, array $fieldInfo): array
    {
        if ($field instanceof TableField) {
            $fieldInfo['valueFormat'] = 'array of row objects';
            $fieldInfo['hint'] = 'Each row keyed by column key.';
            $fieldInfo['columns'] = [];
            foreach ($field->columns as $colKey => $col) {
                $fieldInfo['columns'][] = [
                    'key' => $colKey,
                    'heading' => $col['heading'],
                    'type' => $col['type'],
                ];
            }

            if ($field->minRows) {
                $fieldInfo['minRows'] = $field->minRows;
            }
            if ($field->maxRows) {
                $fieldInfo['maxRows'] = $field->maxRows;
            }
        } elseif ($field instanceof LinkField) {
            $fieldInfo['valueFormat'] = 'link object';
            $fieldInfo['allowedTypes'] = $field->types;
            $types = implode(', ', $field->types);

            if (count($field->types) === 1 && $field->types[0] === 'entry') {
                $fieldInfo['hint'] = 'Type: entry. Value must be an entry ID. Use searchEntries to find valid IDs. Example: {"type": "entry", "value": 123, "label": "My Entry"}.';
            } elseif (count($field->types) === 1 && $field->types[0] === 'asset') {
                $fieldInfo['hint'] = 'Type: asset. Value must be an asset ID. Use searchAssets to find valid IDs. Example: {"type": "asset", "value": 456, "label": "My Asset"}.';
            } else {
                $fieldInfo['hint'] = 'Allowed types: ' . $types . '. Example: {"type": "url", "value": "https://example.com", "label": "Example"}. For entry/asset types, use the element ID as value.';
            }
        } elseif ($field instanceof MoneyField) {
            $fieldInfo['valueFormat'] = 'integer (minor units)';
            $fieldInfo['hint'] = '1990 = 19.90 ' . ($field->currency ?? 'USD') . '.';
            $fieldInfo['currency'] = $field->currency;
        } elseif ($field instanceof JsonField) {
            $fieldInfo['valueFormat'] = 'JSON object or array';
        } elseif ($field instanceof MatrixField) {
            $fieldInfo['valueFormat'] = 'array of block objects';
            $fieldInfo['hint'] = 'Appends by default. Replace all: {"_replace": true, "blocks": [...]}. Clear: [].';

            if ($field->minEntries) {
                $fieldInfo['minEntries'] = $field->minEntries;
            }
            if ($field->maxEntries) {
                $fieldInfo['maxEntries'] = $field->maxEntries;
            }
        } elseif ($field instanceof ContentBlockField) {
            $fieldInfo['valueFormat'] = 'object with sub-field values';
            $fieldInfo['hint'] = 'Do NOT include title/slug keys.';
        }

        return $fieldInfo;
    }

    public function serializeValue(FieldInterface $field, mixed $value, int $depth): mixed
    {
        if ($field instanceof ContentBlockField && $value instanceof ContentBlockElement) {
            return $this->serializeContentBlock($field, $value, $depth);
        }

        if ($field instanceof MatrixField) {
            if ($depth <= 0) {
                return ['_truncated' => true, '_count' => $value->count()];
            }

            return $this->serializeMatrixBlocks($value, $depth - 1);
        }

        if ($value instanceof LinkData) {
            return [
                '_type' => 'link',
                'url' => $value->getUrl(),
                'label' => $value->getLabel(),
                'type' => $value->getType(),
                'target' => $value->target,
            ];
        }

        if ($value instanceof \Money\Money) {
            return [
                '_type' => 'money',
                'amount' => $value->getAmount(),
                'currency' => $value->getCurrency()->getCode(),
            ];
        }

        if ($value instanceof JsonData) {
            return $value->getValue();
        }

        // Table field returns arrays natively
        if (is_array($value)) {
            return $value;
        }

        return $value;
    }

    public function normalizeValue(FieldInterface $field, mixed $value, ?Entry $entry = null): mixed
    {
        if ($field instanceof ContentBlockField && is_array($value)) {
            return $this->normalizeContentBlockValue($value);
        }

        if ($field instanceof LinkField) {
            if (is_int($value) || (is_string($value) && ctype_digit($value))) {
                return ['type' => 'entry', 'value' => (int) $value];
            }
            if (is_array($value)) {
                return $this->normalizeLinkValue($value);
            }
        }

        if ($field instanceof MoneyField && is_array($value) && isset($value['amount'])) {
            return (int) $value['amount'];
        }

        if ($field instanceof TableField && $value === null) {
            return [];
        }

        if ($field instanceof JsonField && is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        if ($field instanceof MatrixField && is_array($value)) {
            // Use the AI-provided handle (may be a custom layout handle) for Matrix merging
            $handle = CoPilot::getInstance()->fieldNormalizer->getCurrentFieldHandle() ?? $field->handle;

            return $this->normalizeMatrixValue($value, $entry, $handle);
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeContentBlock(ContentBlockField $field, ContentBlockElement $block, int $depth): array
    {
        $data = ['_type' => 'contentBlock'];
        $registry = CoPilot::getInstance()->transformerRegistry;

        foreach ($registry->resolveFieldLayoutFields($field->getFieldLayout()) as $resolved) {
            $handle = $resolved['handle'];
            $subField = $resolved['field'];
            $value = $block->getFieldValue($handle);
            $transformer = $registry->getTransformerForField($subField);

            if ($transformer !== null) {
                $data[$handle] = $transformer->serializeValue($subField, $value, $depth);
            } else {
                $data[$handle] = $this->serializeFallback($value);
            }
        }

        return $data;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function serializeMatrixBlocks(mixed $query, int $depth): array
    {
        $blocks = [];
        $position = 0;
        $registry = CoPilot::getInstance()->transformerRegistry;

        foreach ($query->all() as $block) {
            $blockData = [
                '_blockId' => $block->id,
                '_blockType' => $block->getType()->handle,
                '_blockDescription' => $block->getType()->name,
                '_position' => $position,
            ];

            if ($block->getType()->hasTitleField) {
                $blockData['title'] = $block->title;
            }

            $fieldLayout = $block->getFieldLayout();
            if ($fieldLayout) {
                foreach ($registry->resolveFieldLayoutFields($fieldLayout) as $resolved) {
                    $handle = $resolved['handle'];
                    $subField = $resolved['field'];
                    $value = $block->getFieldValue($handle);
                    $transformer = $registry->getTransformerForField($subField);

                    if ($transformer !== null) {
                        $blockData[$handle] = $transformer->serializeValue($subField, $value, $depth);
                    } else {
                        $blockData[$handle] = $this->serializeFallback($value);
                    }
                }
            }

            $blocks[] = $blockData;
            $position++;
        }

        return $blocks;
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

    /**
     * @param array<string, mixed> $value
     * @return array<string, mixed>
     */
    private function normalizeContentBlockValue(array $value): array
    {
        if (isset($value['fields']) && is_array($value['fields'])) {
            unset($value['fields']['title'], $value['fields']['slug']);

            return $value;
        }

        $nativeAttributes = ['title', 'slug'];
        $fields = array_diff_key($value, array_flip($nativeAttributes));

        return ['fields' => $fields];
    }

    /**
     * @param array<string, mixed> $value
     * @return array<string, mixed>
     */
    private function normalizeLinkValue(array $value): array
    {
        $keyMappings = [
            'url' => 'url',
            'entryId' => 'entry',
            'assetId' => 'asset',
            'categoryId' => 'category',
            'email' => 'email',
            'phone' => null,
        ];

        foreach ($keyMappings as $aiKey => $impliedType) {
            if (isset($value[$aiKey]) && !isset($value['value'])) {
                $value['value'] = $value[$aiKey];
                unset($value[$aiKey]);

                if ($impliedType !== null && !isset($value['type'])) {
                    $value['type'] = $impliedType;
                }
            }
        }

        if (!isset($value['type']) && isset($value['value'])) {
            $value['type'] = $this->detectLinkType($value['value']);
        }

        if (!isset($value['type'])) {
            $value['type'] = 'url';
        }

        if (isset($value['value'])) {
            $value['value'] = $this->prefixLinkValue((string) $value['type'], $value['value']);
        }

        return $value;
    }

    private function detectLinkType(mixed $value): string
    {
        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            return 'entry';
        }

        $value = (string) $value;

        if (str_starts_with($value, 'mailto:') || filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return 'email';
        }

        if (str_starts_with($value, 'tel:')) {
            return 'tel';
        }

        if (str_starts_with($value, 'sms:')) {
            return 'sms';
        }

        return 'url';
    }

    private function prefixLinkValue(string $type, mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        return match ($type) {
            'email' => !str_starts_with($value, 'mailto:') && filter_var($value, FILTER_VALIDATE_EMAIL)
                ? 'mailto:' . $value
                : $value,
            'tel' => !str_starts_with($value, 'tel:')
                ? 'tel:' . $value
                : $value,
            'sms' => !str_starts_with($value, 'sms:')
                ? 'sms:' . $value
                : $value,
            default => $value,
        };
    }

    /**
     * @param array<int|string, mixed> $value
     * @return array<string, mixed>
     */
    private function normalizeMatrixValue(array $value, ?Entry $entry, string $fieldHandle): array
    {
        if (isset($value['entries'])) {
            return $value;
        }

        if ($value === []) {
            return [
                'entries' => [],
                'sortOrder' => [],
            ];
        }

        $replaceMode = false;
        if (isset($value['_replace']) && $value['_replace'] === true) {
            $replaceMode = true;
            $value = $value['blocks'] ?? [];
        }

        $firstKey = array_key_first($value);
        if (is_string($firstKey) && str_starts_with($firstKey, 'new')) {
            return $value;
        }

        $newEntries = [];
        $newSortOrder = [];
        $newIndex = 1;

        foreach (array_values($value) as $block) {
            if (!is_array($block)) {
                continue;
            }

            $block = $this->normalizeMatrixBlock($block);
            $key = 'new' . $newIndex++;
            $newSortOrder[] = $key;
            $newEntries[$key] = $block;
        }

        if ($replaceMode || $entry === null) {
            return [
                'entries' => $newEntries,
                'sortOrder' => $newSortOrder,
            ];
        }

        return $this->mergeWithExistingBlocks($entry, $fieldHandle, $newEntries, $newSortOrder);
    }

    /**
     * Normalizes a single Matrix block from AI format to Craft format.
     * Handles: _blockType→type fallback, stripping serialization markers,
     * and restructuring flat blocks into {type, title, fields} format.
     *
     * @param array<string, mixed> $block
     * @return array<string, mixed>
     */
    private function normalizeMatrixBlock(array $block): array
    {
        // Use _blockType as type fallback
        if (!isset($block['type']) && isset($block['_blockType'])) {
            $block['type'] = $block['_blockType'];
        }

        // Strip all serialization markers (keys starting with _)
        foreach (array_keys($block) as $key) {
            if (str_starts_with((string) $key, '_')) {
                unset($block[$key]);
            }
        }

        // If fields are at top level (no 'fields' sub-key), restructure
        if (!isset($block['fields'])) {
            $reserved = ['type', 'title', 'slug'];
            $fields = array_diff_key($block, array_flip($reserved));
            $block = array_intersect_key($block, array_flip($reserved));
            if ($fields !== []) {
                $block['fields'] = $fields;
            }
        }

        // Strip native attributes from fields sub-key (AI sometimes puts title/slug inside fields)
        if (isset($block['fields']) && is_array($block['fields'])) {
            foreach (['title', 'slug'] as $native) {
                if (isset($block['fields'][$native]) && !isset($block[$native])) {
                    $block[$native] = $block['fields'][$native];
                }
                unset($block['fields'][$native]);
            }
        }

        return $block;
    }

    /**
     * @param array<string, mixed> $newEntries
     * @param array<string> $newSortOrder
     * @return array<string, mixed>
     */
    private function mergeWithExistingBlocks(
        Entry $entry,
        string $fieldHandle,
        array $newEntries,
        array $newSortOrder,
    ): array {
        $sortOrder = [];

        try {
            $existingBlocks = $entry->getFieldValue($fieldHandle)->all();
        } catch (\Throwable) {
            $existingBlocks = [];
        }

        foreach ($existingBlocks as $block) {
            $blockId = (string) $block->id;
            $sortOrder[] = $blockId;
        }

        $entries = [];
        foreach ($newSortOrder as $key) {
            $sortOrder[] = $key;
            $entries[$key] = $newEntries[$key];
        }

        return [
            'entries' => $entries,
            'sortOrder' => $sortOrder,
        ];
    }
}
