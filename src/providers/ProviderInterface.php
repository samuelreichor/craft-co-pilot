<?php

namespace samuelreichor\coPilot\providers;

use samuelreichor\coPilot\models\AIResponse;
use samuelreichor\coPilot\models\StreamChunk;

interface ProviderInterface
{
    /**
     * Sends messages + tools to the provider and returns the response.
     *
     * @param string $systemPrompt
     * @param array<int, array{role: string, content: string|array}> $messages
     * @param array<int, array<string, mixed>> $tools Normalized tool definitions
     * @param string|null $model Override model selection
     */
    public function chat(
        string $systemPrompt,
        array $messages,
        array $tools,
        ?string $model = null,
    ): AIResponse;

    /**
     * Sends messages + tools to the provider with streaming response.
     *
     * @param string $systemPrompt
     * @param array<int, array{role: string, content: string|array}> $messages
     * @param array<int, array<string, mixed>> $tools Normalized tool definitions
     * @param string|null $model Override model selection
     * @param callable(StreamChunk): void $onChunk Called for each streamed chunk
     */
    public function chatStream(
        string $systemPrompt,
        array $messages,
        array $tools,
        ?string $model,
        callable $onChunk,
    ): void;

    /**
     * Returns available model identifiers for this provider.
     *
     * @return array<int, string>
     */
    public function getAvailableModels(): array;

    /**
     * Returns the human-readable provider name.
     */
    public function getName(): string;

    /**
     * Returns an inline SVG string for the provider icon.
     */
    public function getIcon(): string;

    /**
     * Returns a fast, cheap model identifier used for lightweight tasks like title generation.
     */
    public function getTitleModel(): string;

    /**
     * Validates that the given API key is functional.
     */
    public function validateApiKey(string $key): bool;
}
