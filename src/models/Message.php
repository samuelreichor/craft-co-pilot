<?php

namespace samuelreichor\coPilot\models;

use samuelreichor\coPilot\enums\MessageRole;

class Message
{
    public MessageRole $role;
    public string|array $content;
    public ?string $toolCallId;
    public ?string $toolName;

    public function __construct(
        MessageRole $role,
        string|array $content,
        ?string $toolCallId = null,
        ?string $toolName = null,
    ) {
        $this->role = $role;
        $this->content = $content;
        $this->toolCallId = $toolCallId;
        $this->toolName = $toolName;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'role' => $this->role->value,
            'content' => $this->content,
            'toolCallId' => $this->toolCallId,
            'toolName' => $this->toolName,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['role'], $data['content'])) {
            throw new \InvalidArgumentException('Message data must contain role and content.');
        }

        $role = MessageRole::tryFrom($data['role']);
        if ($role === null) {
            throw new \InvalidArgumentException("Invalid message role: {$data['role']}");
        }

        return new self(
            role: $role,
            content: $data['content'],
            toolCallId: $data['toolCallId'] ?? null,
            toolName: $data['toolName'] ?? null,
        );
    }
}
