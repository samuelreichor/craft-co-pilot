<?php

namespace samuelreichor\coPilot\tools;

use Craft;
use craft\elements\Entry;
use samuelreichor\coPilot\enums\AuditAction;
use samuelreichor\coPilot\enums\ElementUpdateBehavior;

class PublishEntryTool extends AbstractEntryUpdateTool
{
    public function getName(): string
    {
        return 'publishEntry';
    }

    public function getLabel(): string
    {
        return 'Publish Entry';
    }

    public function getAction(): AuditAction
    {
        return AuditAction::Update;
    }

    public function getDescription(): string
    {
        return 'Publishes an entry by enabling it and saving directly (bypasses draft behavior). Use this tool only when the user explicitly asks to publish, go live, or activate an entry. Optionally update fields in the same operation.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'entryId' => [
                    'type' => 'integer',
                    'description' => 'The Craft entry ID to publish',
                ],
                'siteHandle' => [
                    'type' => 'string',
                    'description' => 'Optional site handle to target a specific site version of the entry. Defaults to the current conversation site.',
                ],
                'fields' => [
                    'type' => 'object',
                    'description' => 'Optional object mapping field handles to new values to update alongside publishing. Same format as updateEntry.',
                ],
            ],
            'required' => ['entryId'],
        ];
    }

    public function execute(array $arguments): array
    {
        $entryId = $arguments['entryId'];
        $siteHandle = $arguments['siteHandle'] ?? $arguments['_siteHandle'] ?? null;

        // Apply any provisional draft before publishing so its changes are merged
        $entry = $this->resolveEntry($entryId, $siteHandle);
        if (is_array($entry)) {
            return $entry;
        }

        if (!$entry->getIsDraft()) {
            $user = Craft::$app->getUser()->getIdentity();
            if ($user) {
                $provisionalDraft = Entry::find()
                    ->provisionalDrafts()
                    ->draftOf($entry)
                    ->draftCreator($user->id)
                    ->siteId($entry->siteId)
                    ->status(null)
                    ->one();

                if ($provisionalDraft) {
                    Craft::$app->getDrafts()->applyDraft($provisionalDraft);
                }
            }
        }

        $fields = $this->normalizeFields($arguments) ?? [];
        $fields['enabled'] = true;

        return $this->performUpdate($entryId, $siteHandle, $fields, ElementUpdateBehavior::DirectSave);
    }
}
