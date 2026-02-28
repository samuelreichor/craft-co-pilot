<?php

namespace samuelreichor\coPilot\tools;

use Craft;
use craft\elements\Entry;
use samuelreichor\coPilot\CoPilot;

class UpdateEntryTool implements ToolInterface
{
    public function getName(): string
    {
        return 'updateEntry';
    }

    public function getDescription(): string
    {
        return 'Updates one or more fields of an existing entry in a single save (one revision). For Matrix fields: by default new blocks are appended. To replace all blocks use {"_replace": true, "blocks": [...]}. To clear all blocks use []. To update a single Matrix block field, pass the block\'s _blockId as entryId.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'entryId' => [
                    'type' => 'integer',
                    'description' => 'The Craft entry ID',
                ],
                'fields' => [
                    'type' => 'object',
                    'description' => 'An object mapping field handles to their new values. Example: {"title": "New Title", "excerpt": "Summary text", "tags": [10, 11]}. Supports all field types (see Field Value Formats).',
                ],
            ],
            'required' => ['entryId', 'fields'],
        ];
    }

    public function execute(array $arguments): array
    {
        $entryId = $arguments['entryId'];

        // AI models sometimes send field values as top-level arguments instead of
        // wrapping them in a "fields" object. Detect and normalize this.
        if (!isset($arguments['fields'])) {
            $reserved = ['entryId'];
            $flatFields = array_diff_key($arguments, array_flip($reserved));
            $fields = $flatFields !== [] ? $flatFields : [];
        } else {
            $fields = $arguments['fields'];
        }

        if (!is_array($fields) || $fields === []) {
            return [
                'error' => 'The "fields" parameter must be a non-empty object.',
                'retryHint' => 'Provide at least one field handle with a value in the "fields" object.',
            ];
        }

        $plugin = CoPilot::getInstance();

        // Permission check
        $guard = $plugin->permissionGuard->canWriteEntry($entryId);
        if (!$guard['allowed']) {
            return [
                'error' => $guard['reason'],
                'retryHint' => null,
            ];
        }

        $entry = Entry::find()->id($entryId)->site('*')->status(null)->drafts(null)->one();
        if (!$entry) {
            return [
                'error' => "Entry #{$entryId} not found.",
                'retryHint' => null,
            ];
        }

        // Strip serialization markers that AI models may echo back
        unset($fields['_type']);

        // Capture old values and set new values
        $diff = [];
        $skippedFields = [];
        $nativeFields = ['title', 'slug'];

        foreach ($fields as $fieldHandle => $value) {
            $oldValue = $this->getFieldValue($entry, $fieldHandle);

            if (in_array($fieldHandle, $nativeFields, true)) {
                $entry->{$fieldHandle} = $value;
            } else {
                $value = CoPilot::getInstance()->fieldNormalizer->normalize($fieldHandle, $value, $entry);

                try {
                    $entry->setFieldValue($fieldHandle, $value);
                } catch (\Throwable $e) {
                    $skippedFields[$fieldHandle] = $e->getMessage();

                    continue;
                }
            }

            $diff[$fieldHandle] = [
                'old' => $oldValue,
                'new' => $value,
            ];
        }

        try {
            $saved = Craft::$app->getElements()->saveElement($entry);
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            $retryHint = null;

            if (stripos($message, 'unknown property') !== false) {
                $retryHint = 'A field handle is incorrect (case-sensitive). Call describeSection to verify the exact handles and retry.';
            }

            return [
                'error' => "Save failed: {$message}",
                'retryHint' => $retryHint,
            ];
        }

        if (!$saved) {
            $errors = $entry->getFirstErrors();

            // Surface nested element errors
            foreach ($fields as $fieldHandle => $value) {
                if (in_array($fieldHandle, $nativeFields, true)) {
                    continue;
                }

                $nestedErrors = $this->collectNestedErrors($entry, $fieldHandle);
                if (!empty($nestedErrors)) {
                    $errors["nestedElementErrors.{$fieldHandle}"] = $nestedErrors;
                }
            }

            return [
                'error' => 'Failed to save entry.',
                'validationErrors' => $errors,
                'retryHint' => 'Fix the fields listed in validationErrors and retry.',
            ];
        }

        $result = [
            'success' => true,
            'entryId' => $entry->id,
            'entryTitle' => $entry->title,
            'cpEditUrl' => $entry->getCpEditUrl(),
            'updatedFields' => array_keys($diff),
            'diff' => $diff,
            'message' => 'Entry updated successfully. ' . count($diff) . ' field(s) changed in a single revision.',
        ];

        if ($skippedFields !== []) {
            $result['skippedFields'] = $skippedFields;
            $result['message'] .= ' ' . count($skippedFields) . ' field(s) skipped due to invalid handles.';
        }

        return $result;
    }

    /**
     * Collects validation errors from nested elements (Addresses, ContentBlock).
     *
     * @return array<int, array<string, string>>
     */
    private function collectNestedErrors(Entry $entry, string $fieldHandle): array
    {
        $nestedErrors = [];

        try {
            $fieldValue = $entry->getFieldValue($fieldHandle);
        } catch (\Throwable) {
            return [];
        }

        // Address/relational queries with cached results
        if ($fieldValue instanceof \craft\elements\db\ElementQuery) {
            $elements = $fieldValue->getCachedResult() ?? [];
            foreach ($elements as $i => $element) {
                if ($element->hasErrors()) {
                    $nestedErrors[$i] = $element->getFirstErrors();
                }
            }
        }

        // ContentBlock element
        if ($fieldValue instanceof \craft\base\ElementInterface && $fieldValue->hasErrors()) {
            $nestedErrors[] = $fieldValue->getFirstErrors();
        }

        return $nestedErrors;
    }

    private function getFieldValue(Entry $entry, string $fieldHandle): mixed
    {
        if ($fieldHandle === 'title') {
            return $entry->title;
        }

        if ($fieldHandle === 'slug') {
            return $entry->slug;
        }

        try {
            $value = $entry->getFieldValue($fieldHandle);

            if (is_scalar($value) || $value === null) {
                return $value;
            }

            if (is_object($value) && method_exists($value, '__toString')) {
                return (string) $value;
            }

            if (is_array($value)) {
                return $value;
            }

            if ($value instanceof \craft\elements\db\ElementQuery) {
                return $value->ids();
            }

            return '(complex value)';
        } catch (\Throwable) {
            return null;
        }
    }
}
