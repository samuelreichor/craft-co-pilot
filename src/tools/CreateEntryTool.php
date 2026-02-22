<?php

namespace samuelreichor\coPilot\tools;

use Craft;
use craft\base\Element;
use craft\elements\Entry;
use samuelreichor\coPilot\CoPilot;
use samuelreichor\coPilot\enums\SectionAccess;

class CreateEntryTool implements ToolInterface
{
    public function getName(): string
    {
        return 'createEntry';
    }

    public function getDescription(): string
    {
        return 'Creates a new entry as an unpublished draft in a given section. The entry is never published directly. Always fill all fields defined in the entry type schema, especially required fields and ContentBlock fields.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'sectionHandle' => [
                    'type' => 'string',
                    'description' => 'The section handle (e.g. "blog", "pages")',
                ],
                'entryTypeHandle' => [
                    'type' => 'string',
                    'description' => 'The entry type handle (e.g. "article", "default")',
                ],
                'title' => [
                    'type' => 'string',
                    'description' => 'The title for the new entry',
                ],
                'slug' => [
                    'type' => 'string',
                    'description' => 'Optional custom slug. If omitted, Craft generates one from the title.',
                ],
                'fields' => [
                    'description' => 'Optional custom field values as key-value pairs. For text fields: string value. For asset/image fields: array of asset IDs e.g. [123]. For ContentBlock fields: object with sub-field values e.g. {"headline": "Title", "image": [123]}. For Matrix fields: array of block objects e.g. [{"type": "text", "title": "Block Title", "fields": {"richText": "<p>Content</p>"}}]. Each block must include "type" (entry type handle) and "title" if the block type has a title field. Use searchAssets to find asset IDs first.',
                ],
            ],
            'required' => ['sectionHandle', 'entryTypeHandle', 'title'],
        ];
    }

    public function execute(array $arguments): array
    {
        $sectionHandle = $arguments['sectionHandle'];
        $entryTypeHandle = $arguments['entryTypeHandle'];
        $title = $arguments['title'];
        $slug = $arguments['slug'] ?? null;
        $fields = $arguments['fields'] ?? [];

        // Find section
        $section = Craft::$app->getEntries()->getSectionByHandle($sectionHandle);
        if (!$section) {
            return [
                'error' => "Section '{$sectionHandle}' not found.",
                'retryHint' => 'Call listSections to see available sections.',
            ];
        }

        // Find entry type within this section
        $entryType = $this->findEntryType($section->id, $entryTypeHandle);
        if (!$entryType) {
            return [
                'error' => "Entry type '{$entryTypeHandle}' not found in section '{$sectionHandle}'.",
                'retryHint' => 'Call listSections to see available sections and their entry types.',
            ];
        }

        // Permission checks
        $permissionCheck = $this->checkPermissions($section);
        if ($permissionCheck !== null) {
            return [
                'error' => $permissionCheck,
                'retryHint' => null,
            ];
        }

        // Get current user
        $user = Craft::$app->getUser()->getIdentity();
        if (!$user) {
            return [
                'error' => 'Access denied – no authenticated user.',
                'retryHint' => null,
            ];
        }

        // Create entry as draft
        $entry = new Entry();
        $entry->sectionId = $section->id;
        $entry->typeId = $entryType->id;
        $entry->title = $title;
        $entry->authorId = $user->id;

        if ($slug !== null) {
            $entry->slug = $slug;
        }

        // Strip serialization markers that AI models may echo back
        unset($fields['_type']);

        // Set custom fields
        foreach ($fields as $fieldHandle => $value) {
            try {
                $value = CoPilot::getInstance()->fieldNormalizer->normalize($fieldHandle, $value, $entry);
                $entry->setFieldValue($fieldHandle, $value);
            } catch (\Throwable $e) {
                return [
                    'error' => "Invalid field handle '{$fieldHandle}': {$e->getMessage()}",
                    'retryHint' => "Remove or correct the field '{$fieldHandle}' and retry.",
                ];
            }
        }

        // Save as unpublished draft (no canonical entry)
        $entry->setScenario(Element::SCENARIO_ESSENTIALS);
        $success = Craft::$app->getDrafts()->saveElementAsDraft(
            $entry,
            $user->id,
            'AI Agent Draft',
        );

        if (!$success) {
            $errors = $entry->getFirstErrors();

            return [
                'error' => 'Failed to save draft.',
                'validationErrors' => $errors,
                'retryHint' => 'Fix the fields listed in validationErrors and retry.',
            ];
        }

        // Build diff for audit: everything is new
        $createDiff = ['title' => ['old' => null, 'new' => $title]];

        if ($slug !== null) {
            $createDiff['slug'] = ['old' => null, 'new' => $slug];
        }

        foreach ($fields as $fieldHandle => $value) {
            $createDiff[$fieldHandle] = ['old' => null, 'new' => $value];
        }

        return [
            'success' => true,
            'entryId' => $entry->id,
            'entryTitle' => $entry->title,
            'cpEditUrl' => $entry->getCpEditUrl(),
            'draftId' => $entry->draftId,
            'title' => $entry->title,
            'section' => $sectionHandle,
            'type' => $entryTypeHandle,
            'status' => 'draft',
            'diff' => $createDiff,
            'message' => 'Entry created successfully as an unpublished draft.',
        ];
    }

    private function findEntryType(int $sectionId, string $handle): ?\craft\models\EntryType
    {
        $entryTypes = Craft::$app->getEntries()->getEntryTypesBySectionId($sectionId);

        foreach ($entryTypes as $entryType) {
            if ($entryType->handle === $handle) {
                return $entryType;
            }
        }

        return null;
    }

    private function checkPermissions(\craft\models\Section $section): ?string
    {
        $settings = CoPilot::getInstance()->getSettings();

        // Check element type blocklist
        if ($settings->isElementTypeBlocked(Entry::class)) {
            return 'Access denied – entry access is blocked by the data protection settings.';
        }

        // Check section blocklist
        $access = $settings->getSectionAccessLevel($section->uid);

        if ($access === SectionAccess::Blocked) {
            return "Access denied – the section '{$section->name}' is blocked by the data protection settings.";
        }

        if ($access === SectionAccess::ReadOnly) {
            return "Access denied – the section '{$section->name}' is configured as read-only. "
                . 'You can read entries in this section but not create new ones.';
        }

        // Check Craft write permission
        $user = Craft::$app->getUser()->getIdentity();
        if (!$user) {
            return 'Access denied – no authenticated user.';
        }

        if (!$user->can("saveEntries:{$section->uid}")) {
            return "Access denied – you lack the 'Save Entries' permission for the section '{$section->name}'. "
                . 'Contact an admin to request access.';
        }

        return null;
    }
}
