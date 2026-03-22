<?php

namespace samuelreichor\coPilot\tools;

use Craft;
use samuelreichor\coPilot\enums\AuditAction;

class ListSitesTool implements ToolInterface
{
    public function getName(): string
    {
        return 'listSites';
    }

    public function getLabel(): string
    {
        return 'List Sites';
    }

    public function getAction(): AuditAction
    {
        return AuditAction::Read;
    }

    public function getDescription(): string
    {
        return 'Lists all sites configured in Craft CMS with their handles, names, and languages. Use this to discover available sites for multi-site operations like translations.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => new \stdClass(),
        ];
    }

    public function execute(array $arguments): array
    {
        $sites = Craft::$app->getSites()->getAllSites();

        $result = [];
        foreach ($sites as $site) {
            $result[] = [
                'handle' => $site->handle,
                'name' => $site->getName(),
                'language' => $site->language,
                'primary' => $site->primary,
            ];
        }

        return ['sites' => $result];
    }
}
