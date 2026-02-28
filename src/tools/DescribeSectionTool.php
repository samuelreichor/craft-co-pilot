<?php

namespace samuelreichor\coPilot\tools;

use samuelreichor\coPilot\CoPilot;

class DescribeSectionTool implements ToolInterface
{
    public function getName(): string
    {
        return 'describeSection';
    }

    public function getDescription(): string
    {
        return 'Returns field definitions, value formats, and hints for a section. MUST be called before createEntry or updateEntry to know exact field handles and accepted value formats.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'section' => [
                    'type' => 'string',
                    'description' => 'The section handle (from listSections).',
                ],
            ],
            'required' => ['section'],
        ];
    }

    public function execute(array $arguments): array
    {
        $section = $arguments['section'] ?? null;

        if (!is_string($section) || $section === '') {
            return ['error' => 'Missing required parameter: section'];
        }

        return CoPilot::getInstance()->schemaService->getSectionSchema($section);
    }
}
