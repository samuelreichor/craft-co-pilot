<?php

namespace samuelreichor\coPilot\providers;

use Craft;
use craft\helpers\App;
use samuelreichor\coPilot\CoPilot;
use samuelreichor\coPilot\helpers\Logger;
use samuelreichor\coPilot\models\AIResponse;
use samuelreichor\coPilot\models\StreamChunk;

class GeminiProvider implements ProviderInterface
{
    private const API_BASE = 'https://generativelanguage.googleapis.com/v1beta/models/';

    public function chat(
        string $systemPrompt,
        array $messages,
        array $tools,
        ?string $model = null,
    ): AIResponse {
        $settings = CoPilot::getInstance()->getSettings();
        $apiKey = App::env($settings->geminiApiKeyEnvVar);

        if (!$apiKey) {
            return AIResponse::error('Gemini API key not configured. Set the environment variable ' . $settings->geminiApiKeyEnvVar);
        }

        $model = $model ?? $settings->geminiModel;
        $payload = $this->buildPayload($systemPrompt, $messages, $tools);

        Logger::info("Gemini API request: model={$model}");

        return $this->sendRequest($apiKey, $model, $payload);
    }

    public function chatStream(
        string $systemPrompt,
        array $messages,
        array $tools,
        ?string $model,
        callable $onChunk,
    ): void {
        $settings = CoPilot::getInstance()->getSettings();
        $apiKey = App::env($settings->geminiApiKeyEnvVar);

        if (!$apiKey) {
            $onChunk(new StreamChunk('error', error: 'Gemini API key not configured.'));
            return;
        }

        $model = $model ?? $settings->geminiModel;
        $payload = $this->buildPayload($systemPrompt, $messages, $tools);

        Logger::info("Gemini API stream request: model={$model}");

        $this->sendStreamRequest($apiKey, $model, $payload, $onChunk);
    }

    public function getAvailableModels(): array
    {
        return [
            'gemini-2.5-pro',
            'gemini-2.5-flash',
            'gemini-2.5-flash-lite',
            'gemini-3-pro-preview',
            'gemini-3-flash-preview',
        ];
    }

    public function getName(): string
    {
        return 'Google Gemini';
    }

    public function getIcon(): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C12 6.627 6.627 12 0 12c6.627 0 12 5.373 12 12 0-6.627 5.373-12 12-12-6.627 0-12-5.373-12-12z"/></svg>';
    }

    public function validateApiKey(string $key): bool
    {
        $client = Craft::createGuzzleClient();

        try {
            $response = $client->get(self::API_BASE . 'gemini-2.0-flash', [
                'query' => ['key' => $key],
                'timeout' => 10,
            ]);

            return $response->getStatusCode() === 200;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param array<int, array<string, mixed>> $messages
     * @param array<int, array<string, mixed>> $tools
     * @return array<string, mixed>
     */
    private function buildPayload(string $systemPrompt, array $messages, array $tools): array
    {
        $payload = [
            'systemInstruction' => [
                'parts' => [['text' => $systemPrompt]],
            ],
            'contents' => $this->formatMessages($messages),
            // Note: Do NOT set maxOutputTokens — Gemini 2.5 thinking models may return
            // empty responses when a token budget is set (known Google issue).
        ];

        $formattedTools = ToolFormatter::forGemini($tools);
        if (!empty($formattedTools)) {
            $payload['tools'] = $formattedTools;
        }

        return $payload;
    }

    /**
     * @param array<int, array<string, mixed>> $messages
     * @return array<int, array<string, mixed>>
     */
    private function formatMessages(array $messages): array
    {
        $formatted = [];

        foreach ($messages as $message) {
            $role = $message['role'];

            if ($role === 'tool') {
                $formatted[] = [
                    'role' => 'user',
                    'parts' => [
                        [
                            'functionResponse' => [
                                'name' => $message['toolName'] ?? 'unknown',
                                'response' => is_array($message['content'])
                                    ? $message['content']
                                    : ['result' => $message['content']],
                            ],
                        ],
                    ],
                ];
                continue;
            }

            if ($role === 'assistant' && !empty($message['toolCalls'])) {
                $parts = [];

                if (!empty($message['content'])) {
                    $parts[] = ['text' => $message['content']];
                }

                foreach ($message['toolCalls'] as $tc) {
                    $parts[] = [
                        'functionCall' => [
                            'name' => $tc['name'],
                            'args' => (object)($tc['arguments'] ?: []),
                        ],
                    ];
                }

                $formatted[] = [
                    'role' => 'model',
                    'parts' => $parts,
                ];
                continue;
            }

            $geminiRole = $role === 'assistant' ? 'model' : 'user';
            $formatted[] = [
                'role' => $geminiRole,
                'parts' => [
                    [
                        'text' => is_array($message['content'])
                            ? json_encode($message['content'])
                            : $message['content'],
                    ],
                ],
            ];
        }

        return $formatted;
    }

    private function sendRequest(string $apiKey, string $model, array $payload): AIResponse
    {
        $client = Craft::createGuzzleClient();
        $url = self::API_BASE . $model . ':generateContent';

        try {
            $response = $client->post($url, [
                'query' => ['key' => $apiKey],
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
                'timeout' => 120,
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            return $this->parseResponse($data);
        } catch (\Throwable $e) {
            Logger::error('Gemini API error: ' . $e->getMessage());

            return AIResponse::error('Gemini API error: ' . $e->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function parseResponse(array $data): AIResponse
    {
        $inputTokens = $data['usageMetadata']['promptTokenCount'] ?? 0;
        $outputTokens = $data['usageMetadata']['candidatesTokenCount'] ?? 0;

        $candidate = $data['candidates'][0] ?? null;
        if (!$candidate) {
            return AIResponse::error('No response from Gemini.', $inputTokens, $outputTokens);
        }

        $parts = $candidate['content']['parts'] ?? [];
        $textParts = [];
        $toolCalls = [];

        foreach ($parts as $part) {
            if (isset($part['text'])) {
                $textParts[] = $part['text'];
            }

            if (isset($part['functionCall'])) {
                $toolCalls[] = [
                    'id' => 'gemini_' . uniqid(),
                    'name' => $part['functionCall']['name'],
                    'arguments' => $part['functionCall']['args'] ?? [],
                ];
            }
        }

        $text = implode("\n", $textParts) ?: null;
        $finishReason = $candidate['finishReason'] ?? 'unknown';

        // Recover from MALFORMED_FUNCTION_CALL: Gemini sometimes generates Python-style
        // calls like print(default_api.updateEntry(entryId=238, fields={...})) instead of
        // structured functionCall parts. Parse the text to extract the intended tool call.
        if ($finishReason === 'MALFORMED_FUNCTION_CALL' && empty($toolCalls) && $text !== null) {
            $recovered = $this->parseMalformedFunctionCall($text);
            if ($recovered !== null) {
                Logger::info('Gemini MALFORMED_FUNCTION_CALL recovered: ' . $recovered['name']);
                $toolCalls[] = $recovered;
                $text = null;
            } else {
                Logger::warning('Gemini MALFORMED_FUNCTION_CALL could not be recovered from text: ' . mb_substr($text, 0, 300));
            }
        }

        $type = !empty($toolCalls) ? 'tool_call' : 'text';

        Logger::info("Gemini API response: type={$type}, finish_reason={$finishReason}, inputTokens={$inputTokens}, outputTokens={$outputTokens}");

        if ($text === null && empty($toolCalls)) {
            Logger::warning('Gemini API returned empty response: finish_reason=' . $finishReason
                . ', parts=' . count($parts)
                . ', raw_candidate=' . json_encode($candidate));
        }

        if (!empty($toolCalls)) {
            return AIResponse::toolCall($toolCalls, $text, $inputTokens, $outputTokens);
        }

        return AIResponse::text($text ?? '', $inputTokens, $outputTokens);
    }

    /**
     * Gemini uses streamGenerateContent which returns JSON chunks.
     *
     * @param callable(StreamChunk): void $onChunk
     */
    private function sendStreamRequest(string $apiKey, string $model, array $payload, callable $onChunk): void
    {
        $client = Craft::createGuzzleClient();
        $url = self::API_BASE . $model . ':streamGenerateContent?alt=sse';
        $buffer = '';
        $hasTextContent = false;
        $hasToolCalls = false;
        $finishReason = 'unknown';
        $chunksProcessed = 0;

        // Shared line processor for both streaming and buffer flush
        $processLine = function(string $line) use (&$hasTextContent, &$hasToolCalls, &$finishReason, &$chunksProcessed, $onChunk): void {
            $line = trim($line);
            if ($line === '' || !str_starts_with($line, 'data: ')) {
                return;
            }

            $json = json_decode(substr($line, 6), true);
            if (!is_array($json)) {
                Logger::warning('Gemini stream: failed to parse JSON from line: ' . mb_substr($line, 0, 200));
                return;
            }

            $chunksProcessed++;

            // Track finish_reason
            $chunkFinishReason = $json['candidates'][0]['finishReason'] ?? null;
            if ($chunkFinishReason !== null) {
                $finishReason = $chunkFinishReason;
            }

            // Track content types
            $parts = $json['candidates'][0]['content']['parts'] ?? [];
            foreach ($parts as $part) {
                if (isset($part['text']) && $part['text'] !== '') {
                    $hasTextContent = true;
                }
                if (isset($part['functionCall'])) {
                    $hasToolCalls = true;
                }
            }

            $this->processGeminiStreamChunk($json, $onChunk);
        };

        try {
            $client->post($url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'x-goog-api-key' => $apiKey,
                ],
                'body' => json_encode($payload),
                'timeout' => 120,
                'curl' => [
                    CURLOPT_WRITEFUNCTION => function($ch, $data) use (&$buffer, $processLine) {
                        $buffer .= $data;
                        $lines = explode("\n", $buffer);
                        $buffer = (string)array_pop($lines);

                        foreach ($lines as $line) {
                            $processLine($line);
                        }

                        return strlen($data);
                    },
                ],
            ]);

            // Flush any remaining buffer data
            if (trim($buffer) !== '') {
                Logger::warning('Gemini stream: flushing unparsed buffer remainder (' . strlen($buffer) . ' bytes)');
                $processLine($buffer);
                $buffer = '';
            }

            $hasText = $hasTextContent ? 'true' : 'false';
            $hasTools = $hasToolCalls ? 'true' : 'false';

            Logger::info("Gemini stream complete: finish_reason={$finishReason}, hasText={$hasText}, hasToolCalls={$hasTools}, chunks={$chunksProcessed}");

            if (!$hasTextContent && !$hasToolCalls) {
                Logger::warning("Gemini stream returned no text and no tool calls: finish_reason={$finishReason}, chunks={$chunksProcessed}");
            }
        } catch (\Throwable $e) {
            Logger::error('Gemini stream error: ' . $e->getMessage());
            $onChunk(new StreamChunk('error', error: 'Gemini stream error: ' . $e->getMessage()));
        }
    }

    /**
     * @param array<string, mixed> $json
     * @param callable(StreamChunk): void $onChunk
     */
    private function processGeminiStreamChunk(array $json, callable $onChunk): void
    {
        // Usage metadata
        if (isset($json['usageMetadata'])) {
            $onChunk(new StreamChunk(
                'usage',
                inputTokens: $json['usageMetadata']['promptTokenCount'] ?? 0,
                outputTokens: $json['usageMetadata']['candidatesTokenCount'] ?? 0,
            ));
        }

        $parts = $json['candidates'][0]['content']['parts'] ?? [];

        foreach ($parts as $part) {
            if (isset($part['text'])) {
                $type = !empty($part['thought']) ? 'thinking' : 'text_delta';
                $onChunk(new StreamChunk($type, delta: $part['text']));
            }

            if (isset($part['functionCall'])) {
                $onChunk(new StreamChunk(
                    'tool_call',
                    toolCallId: 'gemini_' . uniqid(),
                    toolName: $part['functionCall']['name'],
                    toolArguments: $part['functionCall']['args'] ?? [],
                ));
            }
        }
    }

    /**
     * Attempts to parse a malformed function call from Gemini's text output.
     * Handles patterns like: print(default_api.updateEntry(entryId=238, fields={...}))
     *
     * @return array{id: string, name: string, arguments: array<string, mixed>}|null
     */
    private function parseMalformedFunctionCall(string $text): ?array
    {
        $text = trim($text);

        // Strip print() wrapper
        if (preg_match('/^print\s*\((.+)\)\s*$/s', $text, $m)) {
            $text = trim($m[1]);
        }

        // Match default_api.functionName(...) or functionName(...)
        if (!preg_match('/(?:default_api\.)?(\w+)\s*\((.+)\)\s*$/s', $text, $m)) {
            return null;
        }

        $functionName = $m[1];
        $argsString = $m[2];

        $args = $this->parsePythonKwargs($argsString);
        if ($args === null) {
            return null;
        }

        return [
            'id' => 'gemini_' . uniqid(),
            'name' => $functionName,
            'arguments' => $args,
        ];
    }

    /**
     * Parses Python-style keyword arguments into a PHP associative array.
     * Example: entryId=238, fields={"key": "value"} → ['entryId' => 238, 'fields' => [...]]
     *
     * @return array<string, mixed>|null
     */
    private function parsePythonKwargs(string $input): ?array
    {
        $pairs = [];
        $pos = 0;
        $len = strlen($input);

        while ($pos < $len) {
            // Skip whitespace and commas
            while ($pos < $len && ($input[$pos] === ' ' || $input[$pos] === ',' || $input[$pos] === "\n" || $input[$pos] === "\t")) {
                $pos++;
            }
            if ($pos >= $len) {
                break;
            }

            // Read key (word characters before =)
            $keyStart = $pos;
            while ($pos < $len && ctype_alnum($input[$pos]) || ($pos < $len && $input[$pos] === '_')) {
                $pos++;
            }
            $key = substr($input, $keyStart, $pos - $keyStart);
            if ($key === '' || $pos >= $len || $input[$pos] !== '=') {
                return null;
            }
            $pos++; // skip =

            // Read value with depth tracking for nested structures
            $valueStart = $pos;
            $depth = 0;
            $inString = false;
            $stringChar = null;

            while ($pos < $len) {
                $ch = $input[$pos];

                if ($inString) {
                    if ($ch === '\\' && $pos + 1 < $len) {
                        $pos++; // skip escaped char
                    } elseif ($ch === $stringChar) {
                        $inString = false;
                    }
                } else {
                    if ($ch === '"' || $ch === "'") {
                        $inString = true;
                        $stringChar = $ch;
                    } elseif ($ch === '{' || $ch === '[' || $ch === '(') {
                        $depth++;
                    } elseif ($ch === '}' || $ch === ']' || $ch === ')') {
                        if ($depth === 0) {
                            break;
                        }
                        $depth--;
                    } elseif ($ch === ',' && $depth === 0) {
                        break;
                    }
                }
                $pos++;
            }

            $valueStr = trim(substr($input, $valueStart, $pos - $valueStart));
            $pairs[$key] = $this->convertPythonValue($valueStr);
        }

        return $pairs === [] ? null : $pairs;
    }

    /**
     * Converts a Python-style value string to a PHP value.
     */
    private function convertPythonValue(string $value): mixed
    {
        if ($value === 'True' || $value === 'true') {
            return true;
        }
        if ($value === 'False' || $value === 'false') {
            return false;
        }
        if ($value === 'None' || $value === 'null') {
            return null;
        }
        if (is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }

        // Normalize Python syntax to JSON and try decoding
        $jsonValue = preg_replace('/\bTrue\b/', 'true', $value);
        $jsonValue = preg_replace('/\bFalse\b/', 'false', $jsonValue);
        $jsonValue = preg_replace('/\bNone\b/', 'null', $jsonValue);

        $decoded = json_decode($jsonValue, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        // Return as string, stripping surrounding quotes
        return trim($value, "\"'");
    }
}
