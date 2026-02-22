<?php

namespace samuelreichor\coPilot\tools;

use craft\elements\Entry;
use samuelreichor\coPilot\CoPilot;
use samuelreichor\coPilot\services\TokenEstimator;

class ReadEntryTool implements ToolInterface
{
    public function getName(): string
    {
        return 'readEntry';
    }

    public function getDescription(): string
    {
        return 'Reads a Craft CMS entry with all fields. Returns field values as structured JSON. Checks whether the entry belongs to an allowed section.';
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
                'depth' => [
                    'type' => 'integer',
                    'description' => 'Serialization depth for nested entries/relations. Default: 2. Max: 4.',
                ],
                'fields' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Optional: Load only specific field handles (for token efficiency)',
                ],
            ],
            'required' => ['entryId'],
        ];
    }

    public function execute(array $arguments): array
    {
        $entryId = $arguments['entryId'];
        $plugin = CoPilot::getInstance();
        $settings = $plugin->getSettings();
        $depth = min($arguments['depth'] ?? $settings->defaultSerializationDepth, $settings->maxSerializationDepth);
        $fields = $arguments['fields'] ?? null;

        // Permission check
        $guard = $plugin->permissionGuard->canReadEntry($entryId);
        if (!$guard['allowed']) {
            return ['error' => $guard['reason']];
        }

        $entry = Entry::find()->id($entryId)->status(null)->drafts(null)->one();
        if (!$entry) {
            return ['error' => "Entry #{$entryId} not found."];
        }

        $data = $plugin->contextService->serializeEntry($entry, $depth, $fields);
        if ($data === null) {
            return ['error' => 'Entry serialization was cancelled.'];
        }

        $settings = $plugin->getSettings();
        $data = TokenEstimator::trim($data, $settings->maxContextTokens);

        return $data;
    }
}
