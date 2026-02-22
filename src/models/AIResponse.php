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

    public function __construct(
        string $type,
        ?string $text = null,
        ?array $toolCalls = null,
        ?string $error = null,
        int $inputTokens = 0,
        int $outputTokens = 0,
    ) {
        $this->type = $type;
        $this->text = $text;
        $this->toolCalls = $toolCalls;
        $this->error = $error;
        $this->inputTokens = $inputTokens;
        $this->outputTokens = $outputTokens;
    }

    public static function text(string $text, int $inputTokens = 0, int $outputTokens = 0): self
    {
        return new self('text', $text, null, null, $inputTokens, $outputTokens);
    }

    /**
     * @param array<int, array{id: string, name: string, arguments: array<string, mixed>}> $toolCalls
     */
    public static function toolCall(array $toolCalls, ?string $text = null, int $inputTokens = 0, int $outputTokens = 0): self
    {
        return new self('tool_call', $text, $toolCalls, null, $inputTokens, $outputTokens);
    }

    public static function error(string $error, int $inputTokens = 0, int $outputTokens = 0): self
    {
        return new self('error', null, null, $error, $inputTokens, $outputTokens);
    }
}
