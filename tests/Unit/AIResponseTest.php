<?php

namespace samuelreichor\coPilot\tests\Unit;

use PHPUnit\Framework\TestCase;
use samuelreichor\coPilot\models\AIResponse;

class AIResponseTest extends TestCase
{
    public function testTextResponse(): void
    {
        $response = AIResponse::text('Hello world', 100, 50);

        $this->assertSame('text', $response->type);
        $this->assertSame('Hello world', $response->text);
        $this->assertNull($response->toolCalls);
        $this->assertNull($response->error);
        $this->assertSame(100, $response->inputTokens);
        $this->assertSame(50, $response->outputTokens);
    }

    public function testToolCallResponse(): void
    {
        $toolCalls = [
            ['id' => 'tc_1', 'name' => 'readEntry', 'arguments' => ['entryId' => 42]],
        ];

        $response = AIResponse::toolCall($toolCalls, 'Thinking...', 200, 80);

        $this->assertSame('tool_call', $response->type);
        $this->assertSame('Thinking...', $response->text);
        $this->assertCount(1, $response->toolCalls);
        $this->assertSame('readEntry', $response->toolCalls[0]['name']);
        $this->assertSame(42, $response->toolCalls[0]['arguments']['entryId']);
        $this->assertNull($response->error);
    }

    public function testErrorResponse(): void
    {
        $response = AIResponse::error('API key invalid', 10, 0);

        $this->assertSame('error', $response->type);
        $this->assertNull($response->text);
        $this->assertNull($response->toolCalls);
        $this->assertSame('API key invalid', $response->error);
        $this->assertSame(10, $response->inputTokens);
        $this->assertSame(0, $response->outputTokens);
    }

    public function testToolCallWithNullText(): void
    {
        $toolCalls = [
            ['id' => 'tc_1', 'name' => 'listSections', 'arguments' => []],
        ];

        $response = AIResponse::toolCall($toolCalls);

        $this->assertNull($response->text);
        $this->assertSame('tool_call', $response->type);
    }
}
