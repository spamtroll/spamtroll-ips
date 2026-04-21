<?php
/**
 * @brief       Spamtroll API Client
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
 * Spamtroll API Client
 */
class _Client
{
    /**
     * Production API base URL — pinned. The ACP settings screen no
     * longer exposes a `spamtroll_api_url` field; override via the
     * constructor argument in tests or a custom hook.
     */
    const API_BASE_URL = 'https://api.spamtroll.io/api/v1';

    /**
     * Default API request timeout (seconds).
     */
    const DEFAULT_TIMEOUT = 5;

    /**
     * @var string API key
     */
    protected $apiKey;

    /**
     * @var string Base URL
     */
    protected $baseUrl;

    /**
     * @var int Timeout in seconds
     */
    protected $timeout;

    /**
     * Constructor
     *
     * @param string|null $apiKey  API key (loads from settings if null)
     * @param string|null $baseUrl Base URL (tests only; ACP no longer exposes this)
     * @param int|null    $timeout Timeout (falls back to DEFAULT_TIMEOUT)
     */
    public function __construct(?string $apiKey = null, ?string $baseUrl = null, ?int $timeout = null)
    {
        $this->apiKey = $apiKey ?? \IPS\Settings::i()->spamtroll_api_key;
        $this->baseUrl = rtrim($baseUrl ?? self::API_BASE_URL, '/');
        $this->timeout = $timeout ?? self::DEFAULT_TIMEOUT;
    }

    /**
     * Check if API is configured
     *
     * @return bool
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Test API connection
     *
     * @return Response
     * @throws Exception
     */
    public function testConnection(): Response
    {
        return $this->request('GET', '/scan/status');
    }

    /**
     * Get account usage statistics
     *
     * @return Response
     * @throws Exception
     */
    public function getAccountUsage(): Response
    {
        return $this->request('GET', '/account/usage');
    }

    /**
     * Check content for spam
     *
     * @param string      $content   Content to check
     * @param string      $source    Source type (forum, message, registration)
     * @param string|null $ipAddress IP address
     * @param string|null $username  Username (for registration checks)
     * @param string|null $email     Email (for registration checks)
     * @return Response
     * @throws Exception
     */
    public function checkSpam(
        string $content,
        string $source = 'forum',
        ?string $ipAddress = null,
        ?string $username = null,
        ?string $email = null
    ): Response {
        $data = [
            'content' => $content,
            'source' => $source,
        ];

        if ($ipAddress) {
            $data['ip_address'] = $ipAddress;
        }

        if ($username) {
            $data['username'] = $username;
        }

        if ($email) {
            $data['email'] = $email;
        }

        return $this->request('POST', '/scan/check', $data);
    }

    /**
     * Make API request
     *
     * @param string     $method   HTTP method
     * @param string     $endpoint API endpoint
     * @param array|null $data     Request data for POST
     * @return Response
     * @throws Exception
     */
    protected function request(string $method, string $endpoint, ?array $data = null): Response
    {
        if (!$this->isConfigured()) {
            throw Exception::notConfigured();
        }

        $url = $this->baseUrl . $endpoint;

        // Validate JSON encoding for POST data
        $jsonBody = null;
        if ($method === 'POST' && $data !== null) {
            $jsonBody = json_encode($data);
            if ($jsonBody === false) {
                throw new Exception('Failed to encode request data as JSON', 0);
            }
        }

        $maxAttempts = 3;
        $lastException = null;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            if ($attempt > 0) {
                usleep($attempt * 500000);
            }

            try {
                $request = \IPS\Http\Url::external($url)->request($this->timeout);
                $request = $request->setHeaders([
                    'X-API-Key' => $this->apiKey,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'User-Agent' => 'Spamtroll-IPS/1.0',
                ]);

                if ($method === 'POST' && $jsonBody !== null) {
                    $response = $request->post($jsonBody);
                } else {
                    $response = $request->get();
                }

                $httpCode = $response->httpResponseCode;
                $body = (string) $response;
                $decoded = json_decode($body, true);

                if ($httpCode >= 200 && $httpCode < 300) {
                    return new Response(true, $httpCode, $decoded ?: []);
                }

                // 401 — invalid API key, no retry
                if ($httpCode === 401) {
                    throw Exception::invalidApiKey();
                }

                $errorMessage = 'API error';
                if (isset($decoded['error'])) {
                    $errorMessage = is_array($decoded['error']) ? json_encode($decoded['error']) : (string) $decoded['error'];
                } elseif (isset($decoded['message'])) {
                    $errorMessage = is_array($decoded['message']) ? json_encode($decoded['message']) : (string) $decoded['message'];
                }

                // 429 — rate limited, no retry
                if ($httpCode === 429) {
                    return new Response(false, $httpCode, $decoded ?: [], $errorMessage);
                }

                // 5xx — server error, retry
                if ($httpCode >= 500) {
                    $lastException = new Exception($errorMessage, $httpCode);
                    continue;
                }

                // Other 4xx — client error, no retry
                return new Response(false, $httpCode, $decoded ?: [], $errorMessage);

            } catch (\IPS\Http\Request\Exception $e) {
                $message = $e->getMessage();
                if (stripos($message, 'timeout') !== false) {
                    $lastException = Exception::timeout();
                } else {
                    $lastException = Exception::connectionFailed($message);
                }
                continue;
            } catch (Exception $e) {
                // Re-throw our own exceptions (invalidApiKey etc.) immediately
                throw $e;
            } catch (\Exception $e) {
                $lastException = new Exception('Request failed: ' . $e->getMessage(), 0, null, null, $e);
                continue;
            }
        }

        throw $lastException;
    }

    /**
     * Get base URL
     *
     * @return string
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }
}
