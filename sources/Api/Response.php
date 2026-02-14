<?php
/**
 * @brief       Spamtroll API Response
 * @author      Spamtroll
 * @copyright   (c) 2024 Spamtroll
 * @package     IPS Community Suite
 * @subpackage  Spamtroll Anti-Spam
 * @since       01 Jan 2024
 */

namespace IPS\spamtroll\Api;

/* To prevent PHP errors (extending class does not exist) revealing path */
if (!\defined('\IPS\SUITE_UNIQUE_KEY')) {
    header((isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0') . ' 403 Forbidden');
    exit;
}

/**
 * Spamtroll API Response
 */
class _Response
{
    /**
     * @var bool Success status
     */
    public $success;

    /**
     * @var int HTTP status code
     */
    public $httpCode;

    /**
     * @var array Response data
     */
    public $data;

    /**
     * @var string|null Error message
     */
    public $error;

    /**
     * @var array Scan result data (extracted from nested response)
     */
    protected $scanData;

    /**
     * Constructor
     *
     * @param bool       $success  Success status
     * @param int        $httpCode HTTP status code
     * @param array      $data     Response data
     * @param string|null $error   Error message
     */
    public function __construct(bool $success, int $httpCode, array $data = [], ?string $error = null)
    {
        $this->success = $success;
        $this->httpCode = $httpCode;
        $this->data = $data;
        $this->error = $error;

        // Extract nested data from API response format: {success: true, data: {...}}
        $this->scanData = $data['data'] ?? $data;
    }

    /**
     * Check if content is spam
     *
     * @return bool
     */
    public function isSpam(): bool
    {
        if (!$this->success) {
            return false;
        }

        $status = $this->scanData['status'] ?? 'safe';
        return $status === 'blocked';
    }

    /**
     * Get status (blocked, suspicious, safe)
     *
     * @return string
     */
    public function getStatus(): string
    {
        return $this->scanData['status'] ?? 'safe';
    }

    /**
     * Get spam score normalized to 0-1 range
     * API uses 0-15+ scale, we normalize to 0-1
     *
     * @return float
     */
    public function getSpamScore(): float
    {
        $rawScore = (float) ($this->scanData['spam_score'] ?? 0.0);
        // Normalize: 15+ = 1.0, 0 = 0.0
        return min(1.0, max(0.0, $rawScore / 15.0));
    }

    /**
     * Get raw spam score (API native scale)
     *
     * @return float
     */
    public function getRawSpamScore(): float
    {
        return (float) ($this->scanData['spam_score'] ?? 0.0);
    }

    /**
     * Get detection symbols
     *
     * @return array
     */
    public function getSymbols(): array
    {
        $symbols = $this->scanData['symbols'] ?? [];
        // Extract just symbol names for simple display
        return array_map(function ($s) {
            return \is_array($s) ? ($s['name'] ?? '') : $s;
        }, $symbols);
    }

    /**
     * Get full symbol details
     *
     * @return array
     */
    public function getSymbolDetails(): array
    {
        return $this->scanData['symbols'] ?? [];
    }

    /**
     * Get threat categories
     *
     * @return array
     */
    public function getThreatCategories(): array
    {
        return $this->scanData['threat_categories'] ?? [];
    }

    /**
     * Get request ID
     *
     * @return string|null
     */
    public function getRequestId(): ?string
    {
        return $this->data['request_id'] ?? null;
    }

    /**
     * Get status message
     *
     * @return string|null
     */
    public function getMessage(): ?string
    {
        return $this->data['message'] ?? null;
    }

    /**
     * Check if response indicates valid API connection
     *
     * @return bool
     */
    public function isConnectionValid(): bool
    {
        return $this->success && $this->httpCode >= 200 && $this->httpCode < 300;
    }

    /**
     * Get API usage data (for account/usage endpoint)
     *
     * @return array
     */
    public function getUsageData(): array
    {
        return [
            'requests_today' => $this->data['requests_today'] ?? 0,
            'requests_limit' => $this->data['requests_limit'] ?? 0,
            'requests_remaining' => $this->data['requests_remaining'] ?? 0,
        ];
    }
}
