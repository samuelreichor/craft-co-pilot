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
        return 'Lists all sections the current user can access, including entry types, field definitions, and field format hints. MUST be called before createEntry or updateEntry to know valid section handles, entry type handles, field handles, and accepted value formats.';
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
