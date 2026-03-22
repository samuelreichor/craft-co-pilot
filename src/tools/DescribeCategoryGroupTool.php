<?php

namespace samuelreichor\coPilot\tools;

use samuelreichor\coPilot\CoPilot;
use samuelreichor\coPilot\enums\AuditAction;

class DescribeCategoryGroupTool implements ToolInterface
{
    public function getName(): string
    {
        return 'describeCategoryGroup';
    }

    public function getLabel(): string
    {
        return 'Describe Category Group';
    }

    public function getAction(): AuditAction
    {
        return AuditAction::Read;
    }

    public function getDescription(): string
    {
        return 'Returns field definitions for a category group. Call before createCategory or updateCategory to know exact field handles and accepted value formats.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'groupHandle' => [
                    'type' => 'string',
                    'description' => 'The category group handle (from searchCategories results).',
                ],
            ],
            'required' => ['groupHandle'],
        ];
    }

    public function execute(array $arguments): array
    {
        $groupHandle = $arguments['groupHandle'] ?? null;

        if (!is_string($groupHandle) || $groupHandle === '') {
            return ['error' => 'Missing required parameter: groupHandle'];
        }

        return CoPilot::getInstance()->schemaService->getCategoryGroupSchema($groupHandle);
    }
}
