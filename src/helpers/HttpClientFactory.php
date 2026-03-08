<?php

namespace samuelreichor\coPilot\helpers;

use Craft;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

/**
 * Creates Guzzle HTTP clients with retry middleware for transient failures.
 *
 * Retries on 429 (rate limit) and 5xx (server error) with exponential backoff.
 * Respects Retry-After headers but caps the delay to avoid long waits.
 */
final class HttpClientFactory
{
    private const MAX_RETRIES = 3;
    private const MAX_DELAY_MS = 10_000;
    private const BASE_DELAY_MS = 1_000;

    /**
     * @param array<string, mixed> $config
     */
    public static function create(array $config = []): Client
    {
        $stack = HandlerStack::create();
        $stack->push(Middleware::retry(self::decider(), self::delay()));

        $config['handler'] = $stack;

        return Craft::createGuzzleClient($config);
    }

    /**
     * @return callable(int, Request, ?Response, ?\Throwable): bool
     */
    private static function decider(): callable
    {
        return function(int $retries, Request $request, ?Response $response, ?\Throwable $exception): bool {
            if ($retries >= self::MAX_RETRIES) {
                return false;
            }

            if ($exception instanceof ConnectException) {
                Logger::warning("Connection error, retry {$retries}/{" . self::MAX_RETRIES . '}: ' . $exception->getMessage());

                return true;
            }

            if (!$response) {
                return false;
            }

            $status = $response->getStatusCode();

            // Only retry 429 if Retry-After is reasonable
            if ($status === 429) {
                $retryAfter = self::parseRetryAfterSeconds($response);

                if ($retryAfter !== null && ($retryAfter * 1000) > self::MAX_DELAY_MS) {
                    Logger::warning("Rate limited (429) but Retry-After is {$retryAfter}s — too long, not retrying.");

                    return false;
                }

                Logger::warning("Rate limited (429), retry {$retries}/{" . self::MAX_RETRIES . '}');

                return true;
            }

            if ($status >= 500) {
                Logger::warning("Server error ({$status}), retry {$retries}/{" . self::MAX_RETRIES . '}');

                return true;
            }

            return false;
        };
    }

    /**
     * @return callable(int, ?Response): int
     */
    private static function delay(): callable
    {
        return function(int $retries, ?Response $response): int {
            if ($response) {
                $retryAfter = self::parseRetryAfterSeconds($response);

                if ($retryAfter !== null) {
                    $delayMs = (int) ($retryAfter * 1000);

                    return min($delayMs, self::MAX_DELAY_MS);
                }
            }

            // Exponential backoff: 1s, 2s, 4s
            return min(self::BASE_DELAY_MS * (int) pow(2, $retries), self::MAX_DELAY_MS);
        };
    }

    private static function parseRetryAfterSeconds(Response $response): ?float
    {
        $header = $response->getHeaderLine('Retry-After');

        if ($header === '') {
            return null;
        }

        if (is_numeric($header)) {
            return (float) $header;
        }

        $timestamp = strtotime($header);

        if ($timestamp === false) {
            return null;
        }

        return max(0, $timestamp - time());
    }
}
