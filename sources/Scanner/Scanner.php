<?php

declare(strict_types=1);
/**
 * @brief       Spamtroll scanner
 *
 * @package     IPS Community Suite
 * @subpackage  Spamtroll Anti-Spam
 */

namespace IPS\spamtroll\Scanner;

/* To prevent PHP errors (extending class does not exist) revealing path */
if (!\defined('\IPS\SUITE_UNIQUE_KEY')) {
    header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0') . ' 403 Forbidden');
    exit;
}

use IPS\spamtroll\Log\QuotaLog;
use IPS\spamtroll\Log\Recorder;
use Spamtroll\Sdk\Client;
use Spamtroll\Sdk\Request\CheckSpamRequest;
use Spamtroll\Sdk\Response\CheckSpamResponse;

/**
 * Everything between "here is some text" and "here is what to do about it".
 *
 * Knows about the SDK, the circuit breaker, the error shapes and the policy.
 * Knows nothing about IPS content objects — those stay in the gateway — which
 * is what makes the whole path testable against a fake HttpClientInterface,
 * with the real SDK in between.
 *
 * Every path out of `scan()` returns a Decision. Failures return
 * `Decision::allow()` with a reason; they do not throw. The gateway still
 * wraps this in a catch-all, because "does not throw" has to be enforced by
 * something other than good intentions.
 */
class _Scanner
{
    protected Client $client;

    protected Breaker $breaker;

    protected Recorder $recorder;

    protected QuotaLog $quotaLog;

    protected ?ResponseHeaderSource $headerSource;

    public function __construct(
        Client $client,
        ?Breaker $breaker = null,
        ?Recorder $recorder = null,
        ?QuotaLog $quotaLog = null,
        ?ResponseHeaderSource $headerSource = null,
    ) {
        $this->client = $client;
        $this->breaker = $breaker ?? new Breaker();
        $this->recorder = $recorder ?? new Recorder();
        $this->quotaLog = $quotaLog ?? new QuotaLog();
        $this->headerSource = $headerSource;
    }

    public function recorder(): Recorder
    {
        return $this->recorder;
    }

    public function scan(
        string $content,
        string $source,
        ?string $ipAddress,
        ?string $username = null,
        ?string $email = null,
    ): Decision {
        if ($this->breaker->isOpen()) {
            return Decision::allow('breaker_open');
        }

        try {
            $response = $this->client->checkSpam(
                new CheckSpamRequest($content, $source, $ipAddress, $username, $email),
            );
        } catch (\Throwable $t) {
            /* Timeouts, refused connections, 5xx after the last retry,
             * authentication failures — and anything the adapter itself
             * managed to break, which is how a package shipped without
             * vendor/ shows up (a plain \Error). */
            $this->breaker->recordTransportFailure();
            Recorder::note('transport', $t);

            return Decision::allow('transport_error', '', mb_strcut($t->getMessage(), 0, 500));
        }

        $headers = $this->headers();
        $this->breaker->observeRateLimitHeaders($headers);

        /* Quota exhausted is an operational state, not an error: the scan did
         * not happen because it was not paid for. Count it for the AdminCP
         * panel and let the content through. */
        if ($response->httpCode === 402) {
            $this->quotaLog->record($response);
            $error = ApiError::classify($response, $headers);
            $decision = Decision::allow('quota_exceeded', $error['code'] ?: 'QUOTA_EXCEEDED', $error['message']);
            $decision->quotaUsage = $error['usage'] !== [] ? $error['usage'] : $response->getQuotaUsage();

            return $decision;
        }

        if (!$response->success) {
            $error = ApiError::classify($response, $headers);

            if ($response->httpCode === 429 || $error['code'] === 'RATE_LIMITED') {
                $this->breaker->open($error['retryAfter']);
            }

            Recorder::note('api', new \RuntimeException(ApiError::describe($error, $response->httpCode)));

            return Decision::allow('api_error', $error['code'], $error['message']);
        }

        $this->breaker->recordSuccess();

        return $this->verdict($response);
    }

    /**
     * Turn a successful response into an action.
     */
    protected function verdict(CheckSpamResponse $response): Decision
    {
        $score = $response->getSpamScore();

        if (Policy::usesLegacyThresholds()) {
            [$status, $action] = Policy::legacyVerdict($score);
        } else {
            $status = $response->getStatus();
            $action = Policy::actionFor($status, Policy::sensitivity());
        }

        return Decision::verdict(
            $action,
            $status,
            $score,
            $response->getSymbols(),
            $response->getThreatCategories(),
            $response->getSubmissionId(),
        );
    }

    /**
     * @return array<string, string>
     */
    protected function headers(): array
    {
        if ($this->headerSource === null) {
            return [];
        }

        try {
            return $this->headerSource->lastHeaders();
        } catch (\Throwable $t) {
            return [];
        }
    }
}
