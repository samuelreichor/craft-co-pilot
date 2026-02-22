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

    public function addMessage(Message $message): void
    {
        $this->messages[] = $message;
    }

    /**
     * Returns messages as provider-compatible array.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getMessagesArray(): array
    {
        return array_map(fn(Message $m) => $m->toArray(), $this->messages);
    }
}
