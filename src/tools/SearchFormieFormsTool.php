<?php

namespace samuelreichor\coPilot\tools;

use samuelreichor\coPilot\enums\AuditAction;

/**
 * Searches Formie forms by title. Returns form handles for use in Formie fields.
 */
class SearchFormieFormsTool implements ToolInterface
{
    public function getName(): string
    {
        return 'searchFormieForms';
    }

    public function getLabel(): string
    {
        return 'Search Formie Forms';
    }

    public function getAction(): AuditAction
    {
        return AuditAction::Search;
    }

    public function getDescription(): string
    {
        return 'Searches Formie forms by title. Returns form handles that can be used in Formie fields. '
            . 'Call this to find the correct form handle before setting a Formie field value.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'Optional search query to filter forms by title.',
                ],
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments): array
    {
        $formsQuery = \verbb\formie\elements\Form::find(); // @phpstan-ignore class.notFound

        $query = $arguments['query'] ?? null;
        if ($query) {
            $formsQuery->title('*' . $query . '*');
        }

        $forms = $formsQuery->all();

        if (empty($forms)) {
            return ['results' => [], 'message' => 'No forms found.'];
        }

        $results = array_map(fn($form) => [
            'id' => $form->id,
            'title' => $form->title,
            'handle' => $form->handle,
        ], $forms);

        return [
            'results' => $results,
            'total' => count($results),
        ];
    }
}
