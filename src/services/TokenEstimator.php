<?php

namespace samuelreichor\coPilot\services;

/**
 * Estimates token counts and trims context to stay within budget.
 */
class TokenEstimator
{
    /**
     * Estimates the token count of a serialized data structure.
     * Rough rule: 1 token ~ 4 characters.
     *
     * @param array<string, mixed> $data
     */
    public static function estimate(array $data): int
    {
        $json = json_encode($data);

        return (int)ceil(strlen($json) / 4);
    }

    /**
     * Reduces context when it exceeds the token budget.
     * Strategy: Trim Matrix block arrays to the first 5 blocks.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function trim(array $data, int $maxTokens = 8000): array
    {
        $estimated = self::estimate($data);

        if ($estimated <= $maxTokens) {
            return $data;
        }

        if (!isset($data['fields']) || !is_array($data['fields'])) {
            return $data;
        }

        foreach ($data['fields'] as $handle => &$value) {
            if (!is_array($value)) {
                continue;
            }

            // Detect Matrix block arrays by _blockType key
            if (!empty($value) && isset($value[0]['_blockType'])) {
                if (count($value) > 5) {
                    $truncated = array_slice($value, 0, 5);
                    $truncated[] = [
                        '_truncated' => true,
                        '_remainingBlocks' => count($value) - 5,
                        '_hint' => 'Use readEntry with specific fields to load more blocks',
                    ];
                    $value = $truncated;
                }
            }
        }

        return $data;
    }
}
