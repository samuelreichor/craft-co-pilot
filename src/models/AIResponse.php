<?php

namespace samuelreichor\coPilot\models;

class AIResponse
{
    /** @var 'text'|'tool_call'|'error' */
    public string $type;

    public ?string $text;

    /** @var array<int, array{id: string, name: string, arguments: array<string, mixed>}>|null */
    public ?array $toolCalls;

    public ?string $error;

    public int $inputTokens;

    public int $outputTokens;

    /** @var array<int, array<string, mixed>>|null Raw provider response parts (e.g. Gemini 3 thought signatures) */
    public ?array $rawModelParts;

    public function __construct(
        string $type,
        ?string $text = null,
        ?array $toolCalls = null,
        ?string $error = null,
        int $inputTokens = 0,
        int $outputTokens = 0,
        ?array $rawModelParts = null,
    ) {
        $this->type = $type;
        $this->text = $text;
        $this->toolCalls = $toolCalls;
        $this->error = $error;
        $this->inputTokens = $inputTokens;
        $this->outputTokens = $outputTokens;
        $this->rawModelParts = $rawModelParts;
    }

    public static function text(string $text, int $inputTokens = 0, int $outputTokens = 0): self
    {
        return new self('text', $text, null, null, $inputTokens, $outputTokens);
    }

    /**
     * @param array<int, array{id: string, name: string, arguments: array<string, mixed>}> $toolCalls
     * @param array<int, array<string, mixed>>|null $rawModelParts
     */
    public static function toolCall(array $toolCalls, ?string $text = null, int $inputTokens = 0, int $outputTokens = 0, ?array $rawModelParts = null): self
    {
        return new self('tool_call', $text, $toolCalls, null, $inputTokens, $outputTokens, $rawModelParts);
    }

    public static function error(string $error, int $inputTokens = 0, int $outputTokens = 0): self
    {
        return new self('error', null, null, $error, $inputTokens, $outputTokens);
    }
}
