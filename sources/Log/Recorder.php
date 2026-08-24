<?php

declare(strict_types=1);
/**
 * @brief       Spamtroll scan log writer
 *
 * @package     IPS Community Suite
 * @subpackage  Spamtroll Anti-Spam
 */

namespace IPS\spamtroll\Log;

/* To prevent PHP errors (extending class does not exist) revealing path */
if (!\defined('\IPS\SUITE_UNIQUE_KEY')) {
    header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0') . ' 403 Forbidden');
    exit;
}

use IPS\spamtroll\Scanner\Decision;

/**
 * Writes a scan result to `spamtroll_logs`.
 *
 * Split out of the hooks so the scanner has one place to log from and the
 * tests have one thing to substitute. Not final: the suite subclasses it to
 * capture writes instead of reaching for a database.
 */
class _Recorder
{
    public function record(
        Decision $decision,
        ?int $memberId,
        string $contentType,
        ?int $contentId,
        ?string $ipAddress,
        ?string $contentPreview = null,
        ?string $email = null,
    ): void {
        try {
            \IPS\spamtroll\Application::log(
                $memberId,
                $contentType,
                $contentId,
                self::anonymiseIp($ipAddress),
                $decision->status,
                $decision->score,
                $decision->symbols,
                $decision->threats,
                $decision->action,
                $contentPreview,
                $decision->submissionId,
                self::emailHash($email),
            );
        } catch (\Throwable $t) {
            /* A log write must never be the reason a post fails. The gateway
             * would catch this anyway; catching it here keeps the failure
             * from aborting the rest of the scan's bookkeeping. */
            self::note('recorder', $t);
        }
    }

    /**
     * Drop the host part of the address when the admin asked for it: the
     * last octet of an IPv4, everything past the first 64 bits of an IPv6.
     * Enough left to spot a noisy network, not enough to identify a person.
     */
    public static function anonymiseIp(?string $ipAddress): ?string
    {
        if ($ipAddress === null || $ipAddress === '') {
            return $ipAddress;
        }

        try {
            if (!\IPS\Settings::i()->spamtroll_anonymize_ip) {
                return $ipAddress;
            }
        } catch (\Throwable $t) {
            return $ipAddress;
        }

        if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            $octets = explode('.', $ipAddress);
            $octets[3] = '0';

            return implode('.', $octets);
        }

        if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            $packed = inet_pton($ipAddress);
            if ($packed === false) {
                return $ipAddress;
            }
            $masked = substr($packed, 0, 8) . str_repeat("\0", 8);
            $text = inet_ntop($masked);

            return $text === false ? $ipAddress : $text;
        }

        return $ipAddress;
    }

    /**
     * A registration is scanned before the account exists, so its log row has
     * no member id and MemberSync's `DELETE ... WHERE log_member_id = ?` never
     * matched it: deleting an account left its registration scan behind, with
     * the IP address it was made from. The hash gives the extension a second
     * thing to match on without storing the address itself.
     */
    public static function emailHash(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }

        $normalised = mb_strtolower(trim($email));

        return $normalised === '' ? null : hash('sha256', $normalised);
    }

    /**
     * Last-resort logging that cannot itself throw, and cannot blow the
     * message size up either — the message is cut on a character boundary
     * with mb_strcut, because a byte-sliced UTF-8 string has killed a
     * request in a sibling integration before.
     */
    public static function note(string $where, \Throwable $throwable): void
    {
        try {
            \IPS\Log::log(
                'Spamtroll ' . $where . ': ' . mb_strcut($throwable->getMessage(), 0, 500),
                'spamtroll',
            );
        } catch (\Throwable $ignored) {
            /* Nothing left to try. Carry on in silence. */
        }
    }
}
