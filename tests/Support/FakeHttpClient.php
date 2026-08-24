<?php

declare(strict_types=1);

namespace IPS\spamtroll\Tests\Support;

use IPS\spamtroll\Scanner\ResponseHeaderSource;
use Spamtroll\Sdk\Http\HttpClientInterface;
use Spamtroll\Sdk\Http\HttpResponse;

/**
 * The only fake in the scanner tests: the network.
 *
 * Everything above it — request building, retries, status handling, response
 * parsing — is the real SDK. That is the point of the fail-open matrix: it
 * has to fail open against the code that actually ships, not against a
 * convenient impression of it.
 */
final class FakeHttpClient implements HttpClientInterface, ResponseHeaderSource
{
    public int $callCount = 0;

    /** @var array<int, array{method: string, url: string, headers: array<string, string>, body: string|null, timeout: int}> */
    public array $calls = [];

    private int $statusCode;

    private string $body;

    /** @var array<string, string> */
    private array $headers;

    private ?\Throwable $throw;

    private float $delaySeconds;

    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        int $statusCode = 200,
        string $body = '',
        array $headers = [],
        ?\Throwable $throw = null,
        float $delaySeconds = 0.0,
    ) {
        $this->statusCode = $statusCode;
        $this->body = $body;
        $this->headers = $headers;
        $this->throw = $throw;
        $this->delaySeconds = $delaySeconds;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     */
    public static function json(int $statusCode, array $payload, array $headers = []): self
    {
        return new self($statusCode, json_encode($payload) ?: '', $headers);
    }

    public static function throwing(\Throwable $throw): self
    {
        return new self(200, '', [], $throw);
    }

    /**
     * @return array<string, string>
     */
    public function lastHeaders(): array
    {
        return $this->headers;
    }

    /**
     * @param array<string, string> $headers
     */
    public function send(string $method, string $url, array $headers, ?string $body, int $timeout): HttpResponse
    {
        $this->callCount++;
        $this->calls[] = [
            'method' => $method,
            'url' => $url,
            'headers' => $headers,
            'body' => $body,
            'timeout' => $timeout,
        ];

        if ($this->delaySeconds > 0.0) {
            usleep((int) ($this->delaySeconds * 1_000_000));
        }

        if ($this->throw !== null) {
            throw $this->throw;
        }

        return new HttpResponse($this->statusCode, $this->body, $this->headers);
    }
}
