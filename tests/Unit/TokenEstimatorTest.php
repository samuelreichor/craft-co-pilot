<?php

namespace samuelreichor\coPilot\tests\Unit;

use PHPUnit\Framework\TestCase;
use samuelreichor\coPilot\services\TokenEstimator;

class TokenEstimatorTest extends TestCase
{
    public function testEstimateReturnsPositiveInteger(): void
    {
        $data = ['id' => 1, 'title' => 'Test Entry', 'fields' => []];
        $result = TokenEstimator::estimate($data);

        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);
    }

    public function testEstimateScalesWithDataSize(): void
    {
        $small = ['id' => 1];
        $large = ['id' => 1, 'title' => str_repeat('a', 1000)];

        $this->assertLessThan(
            TokenEstimator::estimate($large),
            TokenEstimator::estimate($small),
        );
    }

    public function testTrimReturnsDataUnchangedWhenUnderBudget(): void
    {
        $data = [
            'id' => 1,
            'title' => 'Test',
            'fields' => [
                'excerpt' => 'Short text',
            ],
        ];

        $result = TokenEstimator::trim($data, 50000);

        $this->assertSame($data, $result);
    }

    public function testTrimTruncatesMatrixBlocksWhenOverBudget(): void
    {
        $blocks = [];
        for ($i = 0; $i < 20; $i++) {
            $blocks[] = [
                '_blockType' => 'textBlock',
                '_blockId' => $i,
                '_position' => $i,
                'body' => str_repeat('Long content text. ', 100),
            ];
        }

        $data = [
            'id' => 1,
            'title' => 'Test',
            'fields' => [
                'contentBuilder' => $blocks,
            ],
        ];

        // Use a very small token budget to force truncation
        $result = TokenEstimator::trim($data, 100);

        $contentBuilder = $result['fields']['contentBuilder'];

        // Should have 5 blocks + 1 truncation notice = 6
        $this->assertCount(6, $contentBuilder);

        // Last item should be the truncation notice
        $lastItem = end($contentBuilder);
        $this->assertTrue($lastItem['_truncated']);
        $this->assertEquals(15, $lastItem['_remainingBlocks']);
    }

    public function testTrimHandlesDataWithoutFields(): void
    {
        $data = ['id' => 1, 'title' => 'Test'];

        $result = TokenEstimator::trim($data, 1);

        $this->assertSame($data, $result);
    }

    public function testTrimDoesNotTruncateNonMatrixArrays(): void
    {
        $data = [
            'id' => 1,
            'fields' => [
                'tags' => ['tag1', 'tag2', 'tag3', 'tag4', 'tag5', 'tag6', 'tag7'],
            ],
        ];

        $result = TokenEstimator::trim($data, 1);

        // Non-matrix arrays should not be truncated
        $this->assertCount(7, $result['fields']['tags']);
    }
}
