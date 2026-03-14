<?php

namespace samuelreichor\coPilot\models;

class Conversation
{
    public ?int $id;
    public int $userId;
    public string $title;
    public ?string $contextType;
    public ?int $contextId;
    public ?string $dateCreated;
    public ?string $dateUpdated;

    /** @var Message[] */
    public array $messages = [];

    /** @var array<int, array<string, mixed>> */
    public array $debugLog = [];

    public ?string $lastSystemPrompt = null;

    public function __construct(
        int $userId,
        string $title = 'New conversation',
        ?string $contextType = null,
        ?int $contextId = null,
        ?int $id = null,
        ?string $dateCreated = null,
        ?string $dateUpdated = null,
    ) {
        $this->id = $id;
        $this->userId = $userId;
        $this->title = $title;
        $this->contextType = $contextType;
        $this->contextId = $contextId;
        $this->dateCreated = $dateCreated;
        $this->dateUpdated = $dateUpdated;
    }

    /**
     * @param array<string, mixed> $debugData
     */
    public function addDebugTurn(array $debugData, int $inputTokens, int $outputTokens): void
    {
        $this->lastSystemPrompt = $debugData['systemPrompt'] ?? null;

        $this->debugLog[] = [
            'timestamp' => (new \DateTimeImmutable())->format('c'),
            'model' => $debugData['model'] ?? null,
            'provider' => $debugData['provider'] ?? null,
            'tokens' => ['input' => $inputTokens, 'output' => $outputTokens],
            'iterations' => $debugData['iterations'] ?? null,
            'messages' => $debugData['messages'] ?? [],
        ];
    }

    public function addMessage(Message $message): void
    {
        $this->messages[] = $message;
    }

    /**
     * Replace all messages with the given array and clear the debug log.
     *
     * @param Message[] $messages
     */
    public function replaceMessages(array $messages): void
    {
        $this->messages = $messages;
        $this->debugLog = [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getMessagesArray(): array
    {
        return array_map(fn(Message $m) => $m->toArray(), $this->messages);
    }
}
