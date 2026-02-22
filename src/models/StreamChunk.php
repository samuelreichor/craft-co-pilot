<?php

namespace samuelreichor\coPilot\models;

/**
 * Represents a single chunk emitted during a streaming AI response.
 */
class StreamChunk
{
    /**
     * @param 'thinking'|'text_delta'|'tool_call'|'error'|'usage' $type
     * @param array<string, mixed>|null $toolArguments
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
    ) {
    }
}
