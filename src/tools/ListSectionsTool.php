<?php

namespace samuelreichor\coPilot\tools;

use samuelreichor\coPilot\CoPilot;

class ListSectionsTool implements ToolInterface
{
    public function getName(): string
    {
        return 'listSections';
    }

    public function getDescription(): string
    {
        return 'Lists all sections the current user can access, with entry type handles. Call describeSection for field definitions before creating or updating entries.';
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
        return CoPilot::getInstance()->schemaService->getAccessibleSchema();
    }
}
