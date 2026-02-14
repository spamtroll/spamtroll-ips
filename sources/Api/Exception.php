<?php
/**
 * @brief       Spamtroll API Exception
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
 * Spamtroll API Exception
 */
class _Exception extends \RuntimeException
{
    /**
     * @var int HTTP status code
     */
    public $httpCode;

    /**
     * @var string|null API error code
     */
    public $apiErrorCode;

    /**
     * @var array|null Response data
     */
    public $responseData;

    /**
     * Constructor
     *
     * @param string     $message      Error message
     * @param int        $httpCode     HTTP status code
     * @param string|null $apiErrorCode API error code
     * @param array|null $responseData Response data
     * @param \Throwable|null $previous Previous exception
     */
    public function __construct(
        string $message,
        int $httpCode = 0,
        ?string $apiErrorCode = null,
        ?array $responseData = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $httpCode, $previous);

        $this->httpCode = $httpCode;
        $this->apiErrorCode = $apiErrorCode;
        $this->responseData = $responseData;
    }

    /**
     * Create exception from HTTP response
     *
     * @param int    $httpCode HTTP status code
     * @param array|null $data Response data
     * @return static
     */
    public static function fromResponse(int $httpCode, ?array $data = null): self
    {
        $message = $data['error'] ?? $data['message'] ?? 'Unknown API error';
        $apiErrorCode = $data['code'] ?? null;

        return new static($message, $httpCode, $apiErrorCode, $data);
    }

    /**
     * Create connection exception
     *
     * @param string $error Connection error message
     * @return static
     */
    public static function connectionFailed(string $error): self
    {
        return new static('Connection failed: ' . $error, 0);
    }

    /**
     * Create timeout exception
     *
     * @return static
     */
    public static function timeout(): self
    {
        return new static('Request timed out', 0);
    }

    /**
     * Create invalid API key exception
     *
     * @return static
     */
    public static function invalidApiKey(): self
    {
        return new static('Invalid API key', 401, 'INVALID_API_KEY');
    }

    /**
     * Create not configured exception
     *
     * @return static
     */
    public static function notConfigured(): self
    {
        return new static('API key not configured', 0, 'NOT_CONFIGURED');
    }
}
