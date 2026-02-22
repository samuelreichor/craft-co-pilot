<?php

namespace samuelreichor\coPilot\providers;

/**
 * Normalizes tool definitions to provider-specific formats.
 */
class ToolFormatter
{
    /**
     * Converts internal tool format to OpenAI function calling format.
     *
     * @param array<int, array<string, mixed>> $tools
     * @return array<int, array<string, mixed>>
     */
    public static function forOpenAI(array $tools): array
    {
        return array_map(fn(array $tool) => [
            'type' => 'function',
            'function' => [
                'name' => $tool['name'],
                'description' => $tool['description'],
                'parameters' => $tool['parameters'],
            ],
        ], $tools);
    }

    /**
     * Converts internal tool format to Anthropic tool use format.
     *
     * @param array<int, array<string, mixed>> $tools
     * @return array<int, array<string, mixed>>
     */
    public static function forAnthropic(array $tools): array
    {
        return array_map(fn(array $tool) => [
            'name' => $tool['name'],
            'description' => $tool['description'],
            'input_schema' => $tool['parameters'],
        ], $tools);
    }

    /**
     * Converts internal tool format to Gemini function declarations format.
     *
     * @param array<int, array<string, mixed>> $tools
     * @return array<int, array<string, mixed>>
     */
    public static function forGemini(array $tools): array
    {
        if (empty($tools)) {
            return [];
        }

        return [
            [
                'functionDeclarations' => array_map(function(array $tool) {
                    $declaration = [
                        'name' => $tool['name'],
                        'description' => $tool['description'],
                    ];

                    // Omit parameters for parameterless tools — Gemini requires this
                    if (self::hasProperties($tool['parameters'])) {
                        $declaration['parameters'] = $tool['parameters'];
                    }

                    return $declaration;
                }, $tools),
            ],
        ];
    }

    /**
     * Checks whether a tool parameter schema has actual properties defined.
     *
     * @param array<string, mixed> $parameters
     */
    private static function hasProperties(array $parameters): bool
    {
        $properties = $parameters['properties'] ?? null;

        if ($properties instanceof \stdClass) {
            return (array)$properties !== [];
        }

        return is_array($properties) && $properties !== [];
    }
}
