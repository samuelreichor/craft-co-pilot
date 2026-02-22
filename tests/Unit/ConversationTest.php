<?php

namespace samuelreichor\coPilot\tests\Unit;

use PHPUnit\Framework\TestCase;
use samuelreichor\coPilot\enums\MessageRole;
use samuelreichor\coPilot\models\Conversation;
use samuelreichor\coPilot\models\Message;

class ConversationTest extends TestCase
{
    public function testCreateConversation(): void
    {
        $conversation = new Conversation(
            userId: 1,
            title: 'Test Chat',
            contextType: 'entry',
            contextId: 42,
        );

        $this->assertSame(1, $conversation->userId);
        $this->assertSame('Test Chat', $conversation->title);
        $this->assertSame('entry', $conversation->contextType);
        $this->assertSame(42, $conversation->contextId);
        $this->assertNull($conversation->id);
        $this->assertEmpty($conversation->messages);
    }

    public function testDefaultTitle(): void
    {
        $conversation = new Conversation(userId: 1);

        $this->assertSame('New conversation', $conversation->title);
        $this->assertNull($conversation->contextType);
        $this->assertNull($conversation->contextId);
    }

    public function testAddMessage(): void
    {
        $conversation = new Conversation(userId: 1);

        $message = new Message(
            role: MessageRole::User,
            content: 'Hello',
        );

        $conversation->addMessage($message);

        $this->assertCount(1, $conversation->messages);
        $this->assertSame('Hello', $conversation->messages[0]->content);
    }

    public function testGetMessagesArray(): void
    {
        $conversation = new Conversation(userId: 1);

        $conversation->addMessage(new Message(
            role: MessageRole::User,
            content: 'Question',
        ));

        $conversation->addMessage(new Message(
            role: MessageRole::Assistant,
            content: 'Answer',
        ));

        $array = $conversation->getMessagesArray();

        $this->assertCount(2, $array);
        $this->assertSame('user', $array[0]['role']);
        $this->assertSame('Question', $array[0]['content']);
        $this->assertSame('assistant', $array[1]['role']);
        $this->assertSame('Answer', $array[1]['content']);
    }
}
