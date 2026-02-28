<?php

namespace samuelreichor\coPilot\models;

/**
 * Represents a single chunk emitted during a streaming AI response.
 */
class StreamChunk
{
    /**
     * @param 'thinking'|'text_delta'|'tool_call'|'error'|'usage'|'model_parts' $type
     * @param array<string, mixed>|null $toolArguments
     * @param array<int, array<string, mixed>>|null $rawModelParts Raw provider response parts (e.g. Gemini 3 thought signatures)
     */
    public function __construct(
        public readonly string $type,
        public readonly ?string $delta = null,
        public readonly ?string $toolCallId = null,
        public readonly ?string $toolName = null,
        public readonly ?array $toolArguments = null,
        public readonly ?string $error = null,
        public readonly int $inputTokens = 0,
        public readonly int $outputTokens = 0,
        public readonly ?array $rawModelParts = null,
    ) {
    }
}
