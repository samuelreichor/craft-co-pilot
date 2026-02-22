<?php

namespace samuelreichor\coPilot\tools;

use Craft;
use craft\elements\Entry;
use samuelreichor\coPilot\CoPilot;
use samuelreichor\coPilot\enums\SectionAccess;

class SearchEntriesTool implements ToolInterface
{
    public function getName(): string
    {
        return 'searchEntries';
    }

    public function getDescription(): string
    {
        return 'Searches or lists entries in allowed sections. Call without a query to browse entries (optionally filtered by section). Returns entry summaries with IDs – use readEntry for full content.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'Optional search term (uses Craft Search). Use simple keywords, not full sentences. If no results, try different/broader keywords or omit the query to browse all entries.',
                ],
                'section' => [
                    'type' => 'string',
                    'description' => 'Optional: Restrict to a section (handle)',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Max number of results. Default: 20.',
                ],
                'status' => [
                    'type' => 'string',
                    'enum' => ['live', 'draft', 'pending', 'disabled', 'any'],
                    'description' => 'Entry status filter. Default: live.',
                ],
                'orderBy' => [
                    'type' => 'string',
                    'description' => 'Sort order: score, dateCreated, dateUpdated, title. Default: score.',
                ],
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments): array
    {
        $searchQuery = $arguments['query'] ?? null;
        $sectionHandle = $arguments['section'] ?? null;
        $defaultLimit = CoPilot::getInstance()->getSettings()->defaultSearchLimit;
        $limit = min($arguments['limit'] ?? $defaultLimit, 50);
        $status = $arguments['status'] ?? 'live';
        $orderBy = $arguments['orderBy'] ?? ($searchQuery ? 'score' : 'dateCreated');

        // Validate requested section early
        if ($sectionHandle) {
            $sectionCheck = $this->validateSection($sectionHandle);
            if ($sectionCheck !== null) {
                return $sectionCheck;
            }
        }

        $query = Entry::find()->limit($limit);

        if ($searchQuery) {
            $query->search($searchQuery);
        }

        // Apply status filter
        // In Craft 5, drafts are a separate dimension — Entry::find() defaults to drafts(false).
        if ($status === 'any') {
            $query->status(null)->drafts(null);
        } elseif ($status === 'draft') {
            $query->drafts(true);
        } else {
            $query->status($status);
        }

        // Apply section filter
        if ($sectionHandle) {
            $query->section($sectionHandle);
        }

        // Apply ordering
        $query->orderBy($this->resolveOrderBy($orderBy));

        // Filter to allowed sections only
        $allowedSectionIds = $this->getAllowedSectionIds();
        if (empty($allowedSectionIds)) {
            return [
                'total' => 0,
                'results' => [],
                'error' => 'No accessible sections. The current user may lack entry view permissions.',
                'retryHint' => null,
            ];
        }
        $query->sectionId($allowedSectionIds);

        $total = $query->count();
        $entries = $query->all();

        $results = array_map(fn(Entry $entry) => [
            'id' => $entry->id,
            'title' => $entry->title,
            'section' => $entry->getSection()->handle,
            'type' => $entry->getType()->handle,
            'status' => $entry->getStatus(),
            'dateUpdated' => $entry->dateUpdated?->format('Y-m-d'),
            'url' => $entry->url,
        ], $entries);

        if ($total === 0) {
            // When a search query was used, check if entries exist without the query
            if ($searchQuery) {
                $browseQuery = Entry::find()
                    ->status($status === 'any' ? null : $status)
                    ->sectionId($allowedSectionIds);

                if ($status === 'any') {
                    $browseQuery->drafts(null);
                } elseif ($status === 'draft') {
                    $browseQuery->drafts(true);
                }

                if ($sectionHandle) {
                    $browseQuery->section($sectionHandle);
                }

                $browseCount = $browseQuery->count();

                if ($browseCount > 0) {
                    return [
                        'total' => 0,
                        'results' => [],
                        'hint' => "No entries matched the search query \"{$searchQuery}\", but {$browseCount} entries exist in this section. Retry without a query to browse them.",
                        'retryHint' => 'Retry without the query parameter to browse all entries.',
                    ];
                }
            }

            // Check if entries exist with any status (including drafts)
            if ($status !== 'any') {
                $anyStatusQuery = Entry::find()
                    ->status(null)
                    ->drafts(null)
                    ->sectionId($allowedSectionIds);

                if ($sectionHandle) {
                    $anyStatusQuery = $anyStatusQuery->section($sectionHandle);
                }

                $anyStatusCount = $anyStatusQuery->count();

                if ($anyStatusCount > 0) {
                    return [
                        'total' => 0,
                        'results' => [],
                        'hint' => "No entries with status \"{$status}\", but {$anyStatusCount} entries exist with other statuses. Retry with status \"any\" to find them.",
                        'retryHint' => 'Retry with status: "any"',
                    ];
                }
            }
        }

        return [
            'total' => $total,
            'results' => $results,
        ];
    }

    /**
     * Validates that the requested section exists and is accessible.
     *
     * @return array<string, mixed>|null Error response or null if valid
     */
    private function validateSection(string $sectionHandle): ?array
    {
        $section = Craft::$app->getEntries()->getSectionByHandle($sectionHandle);

        if ($section === null) {
            $allHandles = array_map(
                fn($s) => $s->handle,
                Craft::$app->getEntries()->getAllSections(),
            );

            return [
                'error' => "Section \"{$sectionHandle}\" does not exist.",
                'availableSections' => $allHandles,
                'retryHint' => 'Use one of the available section handles listed above.',
            ];
        }

        $settings = CoPilot::getInstance()->getSettings();
        if ($settings->getSectionAccessLevel($section->uid) === SectionAccess::Blocked) {
            return [
                'error' => "Section \"{$sectionHandle}\" is blocked in CoPilot settings.",
                'retryHint' => null,
            ];
        }

        $guardCheck = CoPilot::getInstance()->permissionGuard->canReadSection($section->uid);
        if (!$guardCheck['allowed']) {
            return [
                'error' => "Access denied for section \"{$sectionHandle}\": " . ($guardCheck['reason'] ?? 'insufficient permissions'),
                'retryHint' => null,
            ];
        }

        return null;
    }

    /**
     * @return int[]
     */
    private function getAllowedSectionIds(): array
    {
        $settings = CoPilot::getInstance()->getSettings();
        $permissionGuard = CoPilot::getInstance()->permissionGuard;
        $sections = Craft::$app->getEntries()->getAllSections();
        $ids = [];

        foreach ($sections as $section) {
            $access = $settings->getSectionAccessLevel($section->uid);
            if ($access === SectionAccess::Blocked) {
                continue;
            }

            $guardCheck = $permissionGuard->canReadSection($section->uid);
            if ($guardCheck['allowed']) {
                $ids[] = $section->id;
            }
        }

        return $ids;
    }

    private function resolveOrderBy(string $orderBy): string
    {
        return match ($orderBy) {
            'dateCreated' => 'elements.dateCreated DESC',
            'dateUpdated' => 'elements.dateUpdated DESC',
            'title' => 'title ASC',
            default => 'score',
        };
    }
}
