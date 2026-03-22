<?php

namespace samuelreichor\coPilot\tools;

use Craft;
use craft\base\Element;
use craft\elements\Entry;
use samuelreichor\coPilot\CoPilot;
use samuelreichor\coPilot\enums\AuditAction;
use samuelreichor\coPilot\enums\ElementCreationBehavior;
use samuelreichor\coPilot\enums\SectionAccess;

class CreateEntryTool implements ToolInterface
{
    public function getName(): string
    {
        return 'createEntry';
    }

    public function getLabel(): string
    {
        return 'Create Entry';
    }

    public function getAction(): AuditAction
    {
        return AuditAction::Create;
    }

    public function getDescription(): string
    {
        return 'Creates a new entry in a given section. The save behavior (draft, direct publish, or disabled) depends on plugin configuration. Always fill all fields defined in the entry type schema, especially required fields and ContentBlock fields.';
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
                    'type' => 'object',
                    'description' => 'Optional custom field values as key-value pairs. For text fields: string value. For asset/image fields: array of asset IDs e.g. [123]. For ContentBlock fields: object with sub-field values e.g. {"headline": "Title", "image": [123]}. For Matrix fields: array of block objects e.g. [{"type": "text", "title": "Block Title", "fields": {"richText": "<p>Content</p>"}}]. Each block must include "type" (entry type handle) and "title" if the block type has a title field. Use searchAssets to find asset IDs first.',
                ],
                'site' => [
                    'type' => 'string',
                    'description' => 'Site handle. Defaults to active site. Use to create entries in a specific site.',
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
        $siteHandle = $arguments['site'] ?? $arguments['_siteHandle'] ?? null;

        // AI models sometimes send field values as top-level arguments instead of
        // wrapping them in a "fields" object. Detect and normalize this.
        if (!isset($arguments['fields'])) {
            $reserved = ['sectionHandle', 'entryTypeHandle', 'title', 'slug', 'site', '_siteHandle'];
            $flatFields = array_diff_key($arguments, array_flip($reserved));
            $fields = $flatFields !== [] ? $flatFields : [];
        } else {
            $fields = $arguments['fields'];
        }

        $section = Craft::$app->getEntries()->getSectionByHandle($sectionHandle);
        if (!$section) {
            return [
                'error' => "Section '{$sectionHandle}' not found.",
                'retryHint' => 'Call listSections to see available sections and describeSection for field details.',
            ];
        }

        $entryType = $this->findEntryType($section->id, $entryTypeHandle);
        if (!$entryType) {
            return [
                'error' => "Entry type '{$entryTypeHandle}' not found in section '{$sectionHandle}'.",
                'retryHint' => 'Call listSections to see available sections and their entry types.',
            ];
        }

        $permissionCheck = $this->checkPermissions($section);
        if ($permissionCheck !== null) {
            return [
                'error' => $permissionCheck,
                'retryHint' => null,
            ];
        }

        $user = Craft::$app->getUser()->getIdentity();
        if (!$user) {
            return [
                'error' => 'Access denied – no authenticated user.',
                'retryHint' => null,
            ];
        }

        if ($siteHandle) {
            $site = Craft::$app->getSites()->getSiteByHandle($siteHandle);
            if (!$site) {
                return [
                    'error' => "Site '{$siteHandle}' not found.",
                    'retryHint' => 'Check available site handles and retry.',
                ];
            }
        }

        $entry = new Entry();
        $entry->sectionId = $section->id;
        $entry->typeId = $entryType->id;
        $entry->title = $title;
        $entry->authorId = $user->id;

        if (isset($site)) {
            $entry->siteId = $site->id;
        }

        if ($slug !== null) {
            $entry->slug = $slug;
        }

        unset($fields['_type']);

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

        $settings = CoPilot::getInstance()->getSettings();
        $creationBehavior = ElementCreationBehavior::tryFrom($settings->elementCreationBehavior)
            ?? ElementCreationBehavior::Draft;

        switch ($creationBehavior) {
            case ElementCreationBehavior::Draft:
                $entry->setScenario(Element::SCENARIO_ESSENTIALS);
                $success = Craft::$app->getDrafts()->saveElementAsDraft(
                    $entry,
                    $user->id,
                    'CoPilot Draft',
                );
                $status = 'draft';
                $behaviorMessage = 'Entry created successfully as an unpublished draft.';

                break;
            case ElementCreationBehavior::DirectSave:
                $entry->enabled = true;
                $success = Craft::$app->getElements()->saveElement($entry);
                $status = 'live';
                $behaviorMessage = 'Entry created and published successfully.';

                break;
            case ElementCreationBehavior::Disabled:
                $entry->enabled = false;
                $success = Craft::$app->getElements()->saveElement($entry);
                $status = 'disabled';
                $behaviorMessage = 'Entry created successfully (disabled, not publicly visible).';

                break;
        }

        if (!$success) {
            $errors = $entry->getFirstErrors();

            return [
                'error' => 'Failed to save entry.',
                'validationErrors' => $errors,
                'retryHint' => 'Fix the fields listed in validationErrors and retry.',
            ];
        }

        $createDiff = ['title' => ['old' => null, 'new' => $title]];

        if ($slug !== null) {
            $createDiff['slug'] = ['old' => null, 'new' => $slug];
        }

        foreach ($fields as $fieldHandle => $value) {
            $createDiff[$fieldHandle] = ['old' => null, 'new' => $value];
        }

        $result = [
            'success' => true,
            'entryId' => $entry->id,
            'entryTitle' => $entry->title,
            'cpEditUrl' => $entry->getCpEditUrl(),
            'title' => $entry->title,
            'section' => $sectionHandle,
            'type' => $entryTypeHandle,
            'status' => $status,
            'diff' => $createDiff,
            'message' => $behaviorMessage,
        ];

        if ($entry->draftId) {
            $result['draftId'] = $entry->draftId;
        }

        return $result;
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

        if ($settings->isElementTypeBlocked(Entry::class)) {
            return 'Access denied – entry access is blocked by the data protection settings.';
        }

        $access = $settings->getSectionAccessLevel($section->uid);

        if ($access === SectionAccess::Blocked) {
            return "Access denied – the section '{$section->name}' is blocked by the data protection settings.";
        }

        if ($access === SectionAccess::ReadOnly) {
            return "Access denied – the section '{$section->name}' is configured as read-only. "
                . 'You can read entries in this section but not create new ones.';
        }

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
