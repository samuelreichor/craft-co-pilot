<?php

namespace samuelreichor\coPilot\tools;

use Craft;
use craft\elements\Tag;
use samuelreichor\coPilot\CoPilot;
use samuelreichor\coPilot\enums\AuditAction;

class SearchTagsTool implements ToolInterface
{
    public function getName(): string
    {
        return 'searchTags';
    }

    public function getLabel(): string
    {
        return 'Search Tags';
    }

    public function getAction(): AuditAction
    {
        return AuditAction::Search;
    }

    public function getDescription(): string
    {
        return 'Searches for tags by title. Returns tag summaries with IDs that can be used in updateEntry or createEntry (pass as [tagId] array for tag relation fields).';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'Search term. Omit to browse all tags.',
                ],
                'group' => [
                    'type' => 'string',
                    'description' => 'Optional: Restrict to a tag group (handle)',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Max number of results. Default: 20.',
                ],
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments): array
    {
        $settings = CoPilot::getInstance()->getSettings();

        if ($settings->isElementTypeBlocked(Tag::class)) {
            return ['total' => 0, 'results' => []];
        }

        $searchQuery = $arguments['query'] ?? null;
        $groupHandle = $arguments['group'] ?? null;
        $defaultLimit = $settings->defaultSearchLimit;
        $limit = min($arguments['limit'] ?? $defaultLimit, 50);

        $query = Tag::find()->limit($limit);

        if ($searchQuery) {
            $query->search($searchQuery);
        }

        if ($groupHandle) {
            $tagGroup = Craft::$app->getTags()->getTagGroupByHandle($groupHandle);
            if (!$tagGroup) {
                $validGroups = array_map(fn($g) => $g->handle, Craft::$app->getTags()->getAllTagGroups());

                return [
                    'total' => 0,
                    'results' => [],
                    'error' => "Tag group \"{$groupHandle}\" not found.",
                    'availableGroups' => $validGroups,
                    'retryHint' => !empty($validGroups)
                        ? 'Use one of the available tag group handles listed above.'
                        : 'No tag groups exist. If this is an entries relation field, use searchEntries instead.',
                ];
            }
            $query->group($groupHandle);
        }

        if ($searchQuery) {
            $query->orderBy('score');
        } else {
            $query->orderBy('elements.dateCreated DESC');
        }

        $total = $query->count();
        $tags = $query->all();

        $results = array_map(fn(Tag $tag) => [
            'id' => $tag->id,
            'title' => $tag->title,
            'slug' => $tag->slug,
            'group' => $tag->getGroup()->handle,
        ], $tags);

        return [
            'total' => $total,
            'results' => $results,
        ];
    }
}
