<?php

namespace samuelreichor\coPilot\services;

use Craft;
use craft\base\Component;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\models\Site;
use samuelreichor\coPilot\CoPilot;
use samuelreichor\coPilot\enums\MessageRole;
use samuelreichor\coPilot\events\RegisterToolsEvent;
use samuelreichor\coPilot\events\ToolCallEvent;
use samuelreichor\coPilot\helpers\Logger;
use samuelreichor\coPilot\models\AIResponse;
use samuelreichor\coPilot\models\Message;
use samuelreichor\coPilot\models\StreamChunk;
use samuelreichor\coPilot\providers\ProviderInterface;
use samuelreichor\coPilot\tools\CreateEntryTool;
use samuelreichor\coPilot\tools\ListSectionsTool;
use samuelreichor\coPilot\tools\ReadAssetTool;
use samuelreichor\coPilot\tools\ReadEntryTool;
use samuelreichor\coPilot\tools\SearchAssetsTool;
use samuelreichor\coPilot\tools\SearchEntriesTool;
use samuelreichor\coPilot\tools\SearchTagsTool;
use samuelreichor\coPilot\tools\SearchUsersTool;
use samuelreichor\coPilot\tools\ToolInterface;
use samuelreichor\coPilot\tools\UpdateEntryTool;
use samuelreichor\coPilot\tools\UpdateFieldTool;

/**
 * Orchestrates the AI agent loop: prompt building, provider calls, tool execution.
 */
class AgentService extends Component
{
    public const EVENT_BEFORE_TOOL_CALL = 'beforeToolCall';
    public const EVENT_AFTER_TOOL_CALL = 'afterToolCall';
    public const EVENT_REGISTER_TOOLS = 'registerTools';

    /** @var ToolInterface[]|null */
    private ?array $tools = null;

    /**
     * Handles a user message and returns the AI response.
     *
     * @param Message[] $conversationHistory
     * @param array<int, array<string, mixed>> $attachments
     * @return array{text: string|null, toolCalls: array<int, array<string, mixed>>|null, inputTokens: int, outputTokens: int}
     */
    public function handleMessage(
        string $userMessage,
        ?int $contextEntryId = null,
        array $conversationHistory = [],
        ?string $model = null,
        array $attachments = [],
        ?string $siteHandle = null,
    ): array {
        $plugin = CoPilot::getInstance();

        Logger::info("handleMessage: userMessage length=" . strlen($userMessage)
            . ", contextEntryId={$contextEntryId}, attachments=" . count($attachments));

        // Build context
        $contextEntry = null;
        if ($contextEntryId) {
            $contextEntry = Entry::find()->id($contextEntryId)->status(null)->drafts(null)->one();
        }

        $site = $this->resolveSite($siteHandle, $contextEntry);
        $systemPrompt = $plugin->systemPromptBuilder->build($contextEntry, $site);

        // Enrich user message with resolved attachment context
        $userMessage = $this->enrichMessageWithAttachments($userMessage, $attachments);

        // Build messages array
        $messages = $this->buildMessagesArray($conversationHistory, $userMessage);

        // Get tool definitions
        $toolDefs = $this->getToolDefinitions();

        // Get provider
        $provider = $plugin->providerService->getActiveProvider();

        $totalInputTokens = 0;
        $totalOutputTokens = 0;
        $iteration = 0;
        /** @var array<int, array{name: string, success: bool, entryId: int|null, entryTitle: string|null, cpEditUrl: string|null}> $executedToolCalls */
        $executedToolCalls = [];

        $maxIterations = $plugin->getSettings()->maxAgentIterations;

        // Agent loop: call provider, execute tools, repeat until text response or max iterations
        while ($iteration < $maxIterations) {
            $iteration++;

            Logger::info("Agent loop iteration {$iteration}/{$maxIterations}, sending " . count($messages) . ' messages to provider');

            $response = $provider->chat($systemPrompt, $messages, $toolDefs, $model);
            $totalInputTokens += $response->inputTokens;
            $totalOutputTokens += $response->outputTokens;

            if ($response->type === 'error') {
                return [
                    'text' => 'Error: ' . $response->error,
                    'toolCalls' => $executedToolCalls !== [] ? $executedToolCalls : null,
                    'inputTokens' => $totalInputTokens,
                    'outputTokens' => $totalOutputTokens,
                ];
            }

            if ($response->type === 'text') {
                $text = $response->text;
                if (($text === null || $text === '') && $executedToolCalls !== []) {
                    Logger::warning("handleMessage: provider returned empty text after tool calls, generating summary fallback");
                    $text = $this->buildToolCallSummary($executedToolCalls);
                }

                Logger::info("handleMessage complete: {$iteration} iterations, {$totalInputTokens} input / {$totalOutputTokens} output tokens");

                return [
                    'text' => $text,
                    'toolCalls' => $executedToolCalls !== [] ? $executedToolCalls : null,
                    'inputTokens' => $totalInputTokens,
                    'outputTokens' => $totalOutputTokens,
                ];
            }

            // Handle tool calls
            if ($response->type === 'tool_call' && $response->toolCalls) {
                // Add an assistant message with tool calls
                $messages[] = [
                    'role' => MessageRole::Assistant->value,
                    'content' => $response->text,
                    'toolCalls' => $response->toolCalls,
                ];

                // Execute each tool call
                foreach ($response->toolCalls as $toolCall) {
                    $result = $this->executeTool($toolCall['name'], $toolCall['arguments']);

                    $executedToolCalls[] = [
                        'name' => $toolCall['name'],
                        'success' => !isset($result['error']),
                        'entryId' => $result['entryId'] ?? null,
                        'entryTitle' => $result['entryTitle'] ?? null,
                        'cpEditUrl' => $result['cpEditUrl'] ?? null,
                    ];

                    // Add a tool result message
                    $messages[] = [
                        'role' => MessageRole::Tool->value,
                        'content' => $result,
                        'toolCallId' => $toolCall['id'],
                        'toolName' => $toolCall['name'],
                        'isError' => isset($result['error']),
                    ];
                }
            }
        }

        return [
            'text' => 'The AI reached the maximum number of tool call iterations. Please try a simpler request.',
            'toolCalls' => $executedToolCalls !== [] ? $executedToolCalls : null,
            'inputTokens' => $totalInputTokens,
            'outputTokens' => $totalOutputTokens,
        ];
    }

    /**
     * Handles a user message with streaming, emitting SSE events via callback.
     *
     * @param Message[] $conversationHistory
     * @param callable(string, array<string, mixed>): void $emit Emits SSE events
     * @param array<int, array<string, mixed>> $attachments
     * @return array{text: string|null, inputTokens: int, outputTokens: int}
     */
    public function handleMessageStream(
        string $userMessage,
        ?int $contextEntryId,
        array $conversationHistory,
        ?string $model,
        callable $emit,
        array $attachments = [],
        ?string $siteHandle = null,
    ): array {
        $plugin = CoPilot::getInstance();

        Logger::info("handleMessageStream: userMessage length=" . strlen($userMessage)
            . ", contextEntryId={$contextEntryId}, attachments=" . count($attachments));

        $contextEntry = null;
        if ($contextEntryId) {
            $contextEntry = Entry::find()->id($contextEntryId)->status(null)->drafts(null)->one();
        }

        $site = $this->resolveSite($siteHandle, $contextEntry);
        $systemPrompt = $plugin->systemPromptBuilder->build($contextEntry, $site);

        // Enrich user message with resolved attachment context
        $userMessage = $this->enrichMessageWithAttachments($userMessage, $attachments);

        $messages = $this->buildMessagesArray($conversationHistory, $userMessage);
        $toolDefs = $this->getToolDefinitions();
        $provider = $plugin->providerService->getActiveProvider();

        $totalInputTokens = 0;
        $totalOutputTokens = 0;
        $fullText = '';
        $iteration = 0;
        $hasFallenBack = false;
        $maxIterations = $plugin->getSettings()->maxAgentIterations;

        while ($iteration < $maxIterations) {
            $iteration++;

            Logger::info("Agent stream loop iteration {$iteration}/{$maxIterations}, sending " . count($messages) . ' messages to provider');

            // Accumulate text from this provider call
            $iterationText = '';
            $iterationToolCalls = [];
            $iterationHadError = false;

            $provider->chatStream(
                $systemPrompt,
                $messages,
                $toolDefs,
                $model,
                function(StreamChunk $chunk) use (&$iterationText, &$iterationToolCalls, &$totalInputTokens, &$totalOutputTokens, &$iterationHadError, $emit): void {
                    switch ($chunk->type) {
                        case 'thinking':
                            $emit('thinking', ['delta' => $chunk->delta]);
                            break;
                        case 'text_delta':
                            $iterationText .= $chunk->delta;
                            $emit('text_delta', ['delta' => $chunk->delta]);
                            break;
                        case 'tool_call':
                            $iterationToolCalls[] = [
                                'id' => $chunk->toolCallId,
                                'name' => $chunk->toolName,
                                'arguments' => $chunk->toolArguments ?? [],
                            ];
                            break;
                        case 'usage':
                            $totalInputTokens += $chunk->inputTokens;
                            $totalOutputTokens += $chunk->outputTokens;
                            break;
                        case 'error':
                            $iterationHadError = true;
                            $emit('error', ['message' => $chunk->error]);
                            break;
                    }
                },
            );

            // If the stream returned nothing at all, fall back to non-streaming, then try alternate model
            if ($iterationText === '' && empty($iterationToolCalls) && !$iterationHadError && !$hasFallenBack) {
                $hasFallenBack = true;

                // 1. Try non-streaming with same model
                Logger::warning("Stream returned empty response on iteration {$iteration}, falling back to non-streaming");
                $fallbackResponse = $provider->chat($systemPrompt, $messages, $toolDefs, $model);
                $totalInputTokens += $fallbackResponse->inputTokens;
                $totalOutputTokens += $fallbackResponse->outputTokens;

                // 2. If still empty, try a different model from the same provider
                if ($this->isEmptyResponse($fallbackResponse)) {
                    $altModel = $this->getAlternateModel($provider, $model);
                    if ($altModel !== null) {
                        Logger::warning("Model also returned empty via non-streaming, retrying with alternate model: {$altModel}");
                        $fallbackResponse = $provider->chat($systemPrompt, $messages, $toolDefs, $altModel);
                        $totalInputTokens += $fallbackResponse->inputTokens;
                        $totalOutputTokens += $fallbackResponse->outputTokens;
                    }
                }

                if ($fallbackResponse->type === 'error') {
                    $emit('error', ['message' => $fallbackResponse->error]);
                    break;
                }

                $iterationText = $fallbackResponse->text ?? '';
                if ($iterationText !== '') {
                    $emit('text_delta', ['delta' => $iterationText]);
                }

                if ($fallbackResponse->type === 'tool_call' && $fallbackResponse->toolCalls) {
                    $iterationToolCalls = $fallbackResponse->toolCalls;
                }
            }

            // No tool calls — we're done
            if (empty($iterationToolCalls)) {
                $fullText .= $iterationText;
                break;
            }

            // Has tool calls — execute them and loop back
            $fullText .= $iterationText;

            $messages[] = [
                'role' => MessageRole::Assistant->value,
                'content' => $iterationText ?: null,
                'toolCalls' => $iterationToolCalls,
            ];

            foreach ($iterationToolCalls as $toolCall) {
                $emit('tool_start', [
                    'id' => $toolCall['id'],
                    'name' => $toolCall['name'],
                ]);

                $result = $this->executeTool($toolCall['name'], $toolCall['arguments']);
                $success = !isset($result['error']);

                if (!$success) {
                    Logger::warning("Stream tool '{$toolCall['name']}' returned error: " . ($result['error'] ?? 'unknown'));
                }

                $emit('tool_end', [
                    'id' => $toolCall['id'],
                    'name' => $toolCall['name'],
                    'success' => $success,
                    'entryId' => $result['entryId'] ?? null,
                    'entryTitle' => $result['entryTitle'] ?? null,
                    'cpEditUrl' => $result['cpEditUrl'] ?? null,
                ]);

                $messages[] = [
                    'role' => MessageRole::Tool->value,
                    'content' => $result,
                    'toolCallId' => $toolCall['id'],
                    'toolName' => $toolCall['name'],
                    'isError' => !$success,
                ];
            }

            // Reset for next iteration text
            $fullText .= '';
        }

        if ($fullText === '') {
            Logger::warning("handleMessageStream produced empty response after {$iteration} iterations, {$totalInputTokens} input / {$totalOutputTokens} output tokens");

            // Provide a clear user-facing message instead of silence
            $fallbackMsg = 'The AI model returned an empty response. This can happen with certain models — please try again or switch to a different model.';
            $emit('text_delta', ['delta' => $fallbackMsg]);
            $fullText = $fallbackMsg;
        } else {
            Logger::info("handleMessageStream complete: {$iteration} iterations, {$totalInputTokens} input / {$totalOutputTokens} output tokens");
        }

        return [
            'text' => $fullText ?: null,
            'inputTokens' => $totalInputTokens,
            'outputTokens' => $totalOutputTokens,
        ];
    }

    /**
     * Returns all registered tools keyed by name.
     *
     * @return array<string, ToolInterface>
     */
    public function getTools(): array
    {
        if ($this->tools !== null) {
            return $this->tools;
        }

        $event = new RegisterToolsEvent();
        $event->tools = [
            new ReadEntryTool(),
            new UpdateFieldTool(),
            new UpdateEntryTool(),
            new CreateEntryTool(),
            new SearchEntriesTool(),
            new SearchAssetsTool(),
            new SearchTagsTool(),
            new SearchUsersTool(),
            new ListSectionsTool(),
            new ReadAssetTool(),
        ];

        $this->trigger(self::EVENT_REGISTER_TOOLS, $event);

        $this->tools = [];
        foreach ($event->tools as $tool) {
            $this->tools[$tool->getName()] = $tool;
        }

        return $this->tools;
    }

    /**
     * Returns tool definitions in normalized format for provider.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getToolDefinitions(): array
    {
        $tools = $this->getTools();

        return array_values(array_map(fn(ToolInterface $tool) => [
            'name' => $tool->getName(),
            'description' => $tool->getDescription(),
            'parameters' => $tool->getParameters(),
        ], $tools));
    }

    /**
     * Executes a tool by name with the given arguments.
     *
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function executeTool(string $toolName, array $arguments): array
    {
        $tools = $this->getTools();

        if (!isset($tools[$toolName])) {
            return ['error' => "Unknown tool: {$toolName}"];
        }

        // Before-event: allow cancellation
        $beforeEvent = new ToolCallEvent();
        $beforeEvent->toolName = $toolName;
        $beforeEvent->params = $arguments;
        $this->trigger(self::EVENT_BEFORE_TOOL_CALL, $beforeEvent);

        if ($beforeEvent->cancel) {
            return ['error' => "Tool call '{$toolName}' was cancelled."];
        }

        Logger::info("Executing tool '{$toolName}' with arguments: " . json_encode($arguments));

        try {
            $result = $tools[$toolName]->execute($arguments);

            if (isset($result['error'])) {
                Logger::warning("Tool '{$toolName}' returned error: {$result['error']}");
            } else {
                Logger::info("Tool '{$toolName}' executed successfully");
            }
        } catch (\Throwable $e) {
            Logger::error("Tool '{$toolName}' failed with exception: {$e->getMessage()}");
            $result = ['error' => "Tool execution failed: {$e->getMessage()}"];
        }

        // After-event: allow modification of result
        $afterEvent = new ToolCallEvent();
        $afterEvent->toolName = $toolName;
        $afterEvent->params = $arguments;
        $afterEvent->result = $result;
        $this->trigger(self::EVENT_AFTER_TOOL_CALL, $afterEvent);

        // Log to audit
        $this->logToolCall($toolName, $arguments, $afterEvent->result ?? $result);

        return $afterEvent->result ?? $result;
    }

    /**
     * Builds a human-readable summary of executed tool calls.
     *
     * @param array<int, array{name: string, success: bool, entryId: int|null, entryTitle: string|null, cpEditUrl: string|null}> $toolCalls
     */
    private function buildToolCallSummary(array $toolCalls): string
    {
        $counts = [];
        foreach ($toolCalls as $call) {
            $name = $call['name'];
            if (!isset($counts[$name])) {
                $counts[$name] = 0;
            }
            $counts[$name]++;
        }

        $parts = [];
        foreach ($counts as $name => $count) {
            $parts[] = $count > 1 ? "{$name} ({$count}x)" : $name;
        }

        return 'Done. Completed: ' . implode(', ', $parts) . '.';
    }

    private const MAX_ATTACHMENTS = 5;
    private const MAX_FILE_SIZE = 102400; // 100 KB
    private const ALLOWED_FILE_EXTENSIONS = ['txt', 'csv', 'json', 'xml', 'md', 'html', 'htm', 'yaml', 'yml', 'log'];

    /**
     * Resolves attachment data (assets, files) and appends context to the user message.
     * Validates permissions and input before processing.
     *
     * @param array<int, array<string, mixed>> $attachments
     */
    private function enrichMessageWithAttachments(string $message, array $attachments): string
    {
        if (empty($attachments)) {
            return $message;
        }

        $plugin = CoPilot::getInstance();
        $contextParts = [];
        $processed = 0;

        foreach ($attachments as $attachment) {
            if ($processed >= self::MAX_ATTACHMENTS) {
                break;
            }

            if (!is_array($attachment)) {
                continue;
            }

            $type = $attachment['type'] ?? '';
            $label = is_string($attachment['label'] ?? null) ? $attachment['label'] : '';

            if ($type === 'asset' && isset($attachment['id'])) {
                $assetId = (int)$attachment['id'];

                // Permission check — same as ReadAssetTool
                $guard = $plugin->permissionGuard->canReadAsset($assetId);
                if (!$guard['allowed']) {
                    continue;
                }

                $asset = Asset::find()->id($assetId)->one();
                if ($asset) {
                    $serialized = $plugin->contextService->serializeAsset($asset);
                    $contextParts[] = "--- Attached Asset: {$asset->filename} ---\n"
                        . json_encode($serialized, JSON_UNESCAPED_SLASHES) . "\n---";
                    $processed++;
                }
            } elseif ($type === 'file' && isset($attachment['content']) && is_string($attachment['content'])) {
                // Validate file extension
                $extension = strtolower(pathinfo($label, PATHINFO_EXTENSION));
                if (!in_array($extension, self::ALLOWED_FILE_EXTENSIONS, true)) {
                    continue;
                }

                $content = $attachment['content'];

                // Reject files exceeding size limit
                if (strlen($content) > self::MAX_FILE_SIZE) {
                    continue;
                }

                $contextParts[] = "--- Attached File: {$label} ---\n{$content}\n---";
                $processed++;
            }
        }

        if (empty($contextParts)) {
            return $message;
        }

        return $message . "\n\n" . implode("\n\n", $contextParts);
    }

    /**
     * @param Message[] $history
     * @return array<int, array<string, mixed>>
     */
    private function buildMessagesArray(array $history, string $userMessage): array
    {
        $messages = [];

        foreach ($history as $msg) {
            $messages[] = $msg->toArray();
        }

        $messages[] = [
            'role' => MessageRole::User->value,
            'content' => $userMessage,
        ];

        return $messages;
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $result
     */
    private function logToolCall(string $toolName, array $params, array $result): void
    {
        try {
            $plugin = CoPilot::getInstance();
            $plugin->auditService->log($toolName, $params, $result);
        } catch (\Throwable $e) {
            Logger::error("Audit log failed: {$e->getMessage()}");
        }
    }

    /**
     * Resolves the site from a handle string, falling back to the context entry's site or current site.
     */
    private function resolveSite(?string $siteHandle, ?Entry $contextEntry): ?Site
    {
        if ($siteHandle) {
            $site = Craft::$app->getSites()->getSiteByHandle($siteHandle);
            if ($site) {
                return $site;
            }
        }

        if ($contextEntry) {
            return $contextEntry->getSite();
        }

        return null;
    }

    private function isEmptyResponse(AIResponse $response): bool
    {
        return ($response->text === null || $response->text === '')
            && ($response->toolCalls === null || $response->toolCalls === []);
    }

    private function getAlternateModel(ProviderInterface $provider, ?string $currentModel): ?string
    {
        $models = $provider->getAvailableModels();
        foreach ($models as $model) {
            if ($model !== $currentModel) {
                return $model;
            }
        }

        return null;
    }
}
