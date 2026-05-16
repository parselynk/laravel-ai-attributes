<?php

declare(strict_types=1);

namespace Parselynk\AiAttributes\Support;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;

class HttpRetrier
{
    /**
     * Execute the given HTTP request with retries on transient failures.
     *
     * Retries on:
     *   - HTTP 429 (rate limit)
     *   - HTTP 5xx (server errors)
     *   - Connection exceptions (timeout, DNS, network)
     *
     * Does NOT retry on:
     *   - HTTP 4xx other than 429 (auth, billing, bad request — caller's fault)
     *
     * Backoff is exponential: base, base*2, base*4, ...
     *
     * @param  Closure():Response  $request  Sends the HTTP request and returns the Response.
     */
    public static function execute(Closure $request, int $maxAttempts = 3, int $baseDelayMs = 1000): Response
    {
        $attempt = 0;
        $lastConnectionException = null;
        $lastResponse = null;

        while ($attempt < $maxAttempts) {
            $attempt++;

            try {
                $response = $request();

                if ($response->successful() || ! self::isRetryableStatus($response->status())) {
                    return $response;
                }

                $lastResponse = $response;
                $lastConnectionException = null;
            } catch (ConnectionException $e) {
                $lastConnectionException = $e;
                $lastResponse = null;
            }

            if ($attempt < $maxAttempts) {
                usleep(self::backoffMicroseconds($attempt, $baseDelayMs));
            }
        }

        if ($lastConnectionException !== null) {
            throw $lastConnectionException;
        }

        return $lastResponse;
    }

    protected static function isRetryableStatus(int $status): bool
    {
        return $status === 429 || ($status >= 500 && $status < 600);
    }

    protected static function backoffMicroseconds(int $attempt, int $baseDelayMs): int
    {
        return $baseDelayMs * (2 ** ($attempt - 1)) * 1000;
    }
}
