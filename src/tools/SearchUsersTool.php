<?php

namespace samuelreichor\coPilot\tools;

use Craft;
use craft\elements\User;
use samuelreichor\coPilot\CoPilot;

class SearchUsersTool implements ToolInterface
{
    public function getName(): string
    {
        return 'searchUsers';
    }

    public function getDescription(): string
    {
        return 'Searches for users by name or email. Returns user summaries with IDs that can be used in updateField or createEntry (pass as [userId] array for user relation fields).';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'Search term (searches name and email). Omit to browse all users.',
                ],
                'group' => [
                    'type' => 'string',
                    'description' => 'Optional: Restrict to a user group (handle)',
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

        if ($settings->isElementTypeBlocked(User::class)) {
            return ['total' => 0, 'results' => []];
        }

        $currentUser = Craft::$app->getUser()->getIdentity();
        if (!$currentUser || !$currentUser->can('viewUsers')) {
            return ['total' => 0, 'results' => []];
        }

        $searchQuery = $arguments['query'] ?? null;
        $groupHandle = $arguments['group'] ?? null;
        $defaultLimit = $settings->defaultSearchLimit;
        $limit = min($arguments['limit'] ?? $defaultLimit, 50);

        $query = User::find()->status('active')->limit($limit);

        if ($searchQuery) {
            $query->search($searchQuery);
        }

        if ($groupHandle) {
            $query->group($groupHandle);
        }

        if ($searchQuery) {
            $query->orderBy('score');
        } else {
            $query->orderBy('elements.dateCreated DESC');
        }

        $total = $query->count();
        $users = $query->all();

        $results = array_map(fn(User $user) => [
            'id' => $user->id,
            'name' => $user->fullName ?? $user->username,
            'email' => $user->email,
            'status' => $user->getStatus(),
        ], $users);

        return [
            'total' => $total,
            'results' => $results,
        ];
    }
}
