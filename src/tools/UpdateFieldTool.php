<?php

namespace samuelreichor\coPilot\tools;

use Craft;
use craft\elements\Entry;
use samuelreichor\coPilot\CoPilot;

class UpdateFieldTool implements ToolInterface
{
    public function getName(): string
    {
        return 'updateField';
    }

    public function getDescription(): string
    {
        return 'Updates a single field of an existing entry. Changes are saved directly so the editor can review them inline. Craft automatically keeps a revision for rollback. For Matrix fields: by default new blocks are appended. To replace all blocks use {"_replace": true, "blocks": [...]}. To clear all blocks use [].';
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
                'fieldHandle' => [
                    'type' => 'string',
                    'description' => "The field handle (e.g. 'title', 'excerpt', 'metaDescription')",
                ],
                'value' => [
                    'description' => 'The new value. For CKEditor: HTML string. For Plain Text: string. For Assets: array of asset IDs e.g. [123]. For Matrix: array of block objects.',
                ],
            ],
            'required' => ['entryId', 'fieldHandle', 'value'],
        ];
    }

    public function execute(array $arguments): array
    {
        $entryId = $arguments['entryId'];
        $fieldHandle = $arguments['fieldHandle'];
        $value = $arguments['value'];

        $plugin = CoPilot::getInstance();

        // Permission check
        $guard = $plugin->permissionGuard->canWriteEntry($entryId);
        if (!$guard['allowed']) {
            return [
                'error' => $guard['reason'],
                'retryHint' => null,
            ];
        }

        $entry = Entry::find()->id($entryId)->status(null)->drafts(null)->one();
        if (!$entry) {
            return [
                'error' => "Entry #{$entryId} not found.",
                'retryHint' => null,
            ];
        }

        // Capture old value for diff
        $oldValue = $this->getFieldValue($entry, $fieldHandle);

        // Handle native fields
        if ($fieldHandle === 'title') {
            $entry->title = $value;
        } elseif ($fieldHandle === 'slug') {
            $entry->slug = $value;
        } else {
            // Custom field — normalize complex field types (pass entry for safe Matrix merging)
            $value = CoPilot::getInstance()->fieldNormalizer->normalize($fieldHandle, $value, $entry);

            try {
                $entry->setFieldValue($fieldHandle, $value);
            } catch (\Throwable $e) {
                return [
                    'error' => "Invalid field handle '{$fieldHandle}': {$e->getMessage()}",
                    'retryHint' => "Remove or correct the field '{$fieldHandle}' and retry.",
                ];
            }
        }

        try {
            $saved = Craft::$app->getElements()->saveElement($entry);
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            $retryHint = null;

            if (stripos($message, 'unknown property') !== false) {
                $retryHint = 'The field handle is incorrect (case-sensitive). Call listSections to verify the exact handle and retry.';
            }

            return [
                'error' => "Save failed: {$message}",
                'retryHint' => $retryHint,
            ];
        }

        if (!$saved) {
            $errors = $entry->getFirstErrors();

            // Surface nested element errors (e.g. Address validation details)
            $nestedErrors = $this->collectNestedErrors($entry, $fieldHandle);
            if (!empty($nestedErrors)) {
                $errors['nestedElementErrors'] = $nestedErrors;
            }

            return [
                'error' => 'Failed to save entry.',
                'validationErrors' => $errors,
                'retryHint' => 'Fix the fields listed in validationErrors and retry.',
            ];
        }

        return [
            'success' => true,
            'entryId' => $entry->id,
            'entryTitle' => $entry->title,
            'cpEditUrl' => $entry->getCpEditUrl(),
            'fieldHandle' => $fieldHandle,
            'diff' => [
                'old' => $oldValue,
                'new' => $value,
            ],
            'message' => "Field '{$fieldHandle}' updated successfully. A revision has been created for rollback.",
        ];
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
