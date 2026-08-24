<?php

declare(strict_types=1);
/**
 * @brief       Spamtroll gateway — the only entry point for hooks
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

use IPS\spamtroll\Log\Recorder;
use Spamtroll\Sdk\Request\CheckSpamRequest;

/**
 * The fail-open boundary.
 *
 * Every public method here is total: it catches `\Throwable` — not
 * `\Exception`, so a `\Error` from a package shipped without vendor/ and a
 * `\TypeError` from a framework signature change are covered too — and
 * answers with the permissive result. Hooks call nothing else, which is the
 * point: fail-open stops being a matter of remembering the right `catch` in
 * each hook and becomes a property of where the code lives.
 *
 * What that replaces: two hooks with two different catch sets, one of which
 * caught only SpamtrollException, so a missing vendor/ directory took down
 * registration entirely. Both also wrapped the framework's passthrough
 * boilerplate around bodies that did more than call the parent, so a
 * \RuntimeException from anywhere inside made the hook call the parent a
 * second time — a duplicated post row, a duplicated spam-service query.
 */
final class _Gateway
{
    /** Public methods that are plumbing rather than a scan path. */
    public const NON_SCAN_METHODS = ['setScanner', 'scanner'];

    private static ?Scanner $scanner = null;

    /**
     * Replace the scanner, or pass null to go back to the configured one.
     * Used by the suite; nothing shipped calls it.
     */
    public static function setScanner(?Scanner $scanner): void
    {
        static::$scanner = $scanner;
    }

    public static function scanner(): Scanner
    {
        if (static::$scanner === null) {
            static::$scanner = ClientFactory::interactiveScanner();
        }

        return static::$scanner;
    }

    /**
     * Scan a comment and act on the verdict.
     *
     * Called after the parent has already created the row: we never block at
     * the database level, we hide afterwards. That matches the Suite's own
     * antispam flow and keeps callers that expect an object back working.
     *
     * @param mixed $result Whatever parent::create() returned
     * @param mixed $item The content item the comment belongs to
     * @param mixed $comment The comment body as passed to create()
     * @param mixed $first True when this is the item's first comment
     * @param mixed $member Author, or null for the logged-in member
     * @param mixed $ipAddress Author IP, or null to detect
     */
    public static function applyToComment($result, $item, $comment, $first, $member, $ipAddress): Decision
    {
        try {
            return static::commentDecision($result, $item, $comment, $first, $member, $ipAddress);
        } catch (\Throwable $t) {
            Recorder::note('gateway/comment', $t);

            return Decision::allow('gateway_error');
        }
    }

    /**
     * Scan a registration and apply the verdict to the member.
     *
     * Returns whatever `\IPS\Member::spamService()` should return, which is
     * either the parent's own answer or 4 (deny). Actions other than denial
     * are performed on the member here rather than signalled by a code,
     * because the Suite performs the side effects for codes 2, 3 and 5 inside
     * spamService() itself and its caller looks at nothing but `== 4`
     * (docs/SUITE-FACTS.md, U4c).
     *
     * @param mixed $type Request type, as passed to spamService()
     * @param mixed $emailAddress Address to check, or null for the member's own
     * @param mixed $parentResult
     *
     * @return mixed
     */
    public static function applyToRegistration(\IPS\Member $member, $type, $emailAddress, $parentResult)
    {
        try {
            $email = \is_scalar($emailAddress) && (string) $emailAddress !== '' ? (string) $emailAddress : null;

            return static::registrationResult($member, \is_scalar($type) ? (string) $type : 'register', $email, $parentResult);
        } catch (\Throwable $t) {
            Recorder::note('gateway/registration', $t);

            return $parentResult;
        }
    }

    /**
     * Scan arbitrary text as a forum post. The scan path the fail-open matrix
     * exercises directly.
     */
    public static function scanComment(string $text, ?\IPS\Member $member, ?string $ipAddress, string $source = CheckSpamRequest::SOURCE_FORUM): Decision
    {
        try {
            if ($text === '') {
                return Decision::allow('empty');
            }

            return static::scanner()->scan(
                $text,
                $source,
                $ipAddress,
                $member !== null ? ($member->name ?: null) : null,
                $member !== null ? ($member->email ?: null) : null,
            );
        } catch (\Throwable $t) {
            Recorder::note('gateway/scan', $t);

            return Decision::allow('gateway_error');
        }
    }

    /**
     * Scan a would-be member's name and address.
     */
    public static function scanRegistration(\IPS\Member $member, ?string $emailAddress, ?string $ipAddress): Decision
    {
        try {
            $email = $emailAddress ?: ($member->email ?: null);
            $content = trim($member->name . ' ' . (string) $email);

            if ($content === '') {
                return Decision::allow('empty');
            }

            return static::scanner()->scan(
                $content,
                CheckSpamRequest::SOURCE_REGISTRATION,
                $ipAddress,
                $member->name ?: null,
                $email,
            );
        } catch (\Throwable $t) {
            Recorder::note('gateway/scan', $t);

            return Decision::allow('gateway_error');
        }
    }

    /* ------------------------------------------------------------------ *
     * Everything below runs inside one of the catch-alls above.           *
     * ------------------------------------------------------------------ */

    /**
     * @param mixed $result
     * @param mixed $item
     * @param mixed $comment
     * @param mixed $first
     * @param mixed $member
     * @param mixed $ipAddress
     */
    private static function commentDecision($result, $item, $comment, $first, $member, $ipAddress): Decision
    {
        if (!\IPS\spamtroll\Application::isEnabled()) {
            return Decision::allow('disabled');
        }

        /* Private messages route through Comment::create() too. They are not
         * scanned: reading members' mail surprised admins and did not earn
         * its share of the quota. Matched by type, not by looking for
         * "Messenger" in the class name — a third-party application with that
         * word in its namespace used to be skipped by accident. */
        if ($item instanceof \IPS\core\Messenger\Conversation) {
            return Decision::allow('private_message');
        }

        if (!\IPS\Settings::i()->spamtroll_check_posts) {
            return Decision::allow('disabled');
        }

        $author = $member instanceof \IPS\Member ? $member : \IPS\Member::loggedIn();
        if (\IPS\spamtroll\Application::shouldBypass($author)) {
            return Decision::allow('bypassed');
        }

        $text = trim(strip_tags(\is_scalar($comment) ? (string) $comment : ''));
        if ($text === '') {
            return Decision::allow('empty');
        }

        $ip = \is_string($ipAddress) && $ipAddress !== '' ? $ipAddress : self::requestIp();

        $decision = static::scanComment($text, $author, $ip);

        if ($decision->scanned()) {
            static::scanner()->recorder()->record(
                $decision,
                $author->member_id ?: null,
                'post',
                self::contentId($result),
                $ip,
                mb_substr($text, 0, 500),
            );
        }

        if ($decision->wantsHiding()) {
            static::hide($result, $item, (bool) $first);
        }

        return $decision;
    }

    /**
     * @param mixed $parentResult
     *
     * @return mixed
     */
    private static function registrationResult(\IPS\Member $member, string $type, ?string $emailAddress, $parentResult)
    {
        if (!\IPS\spamtroll\Application::isEnabled()) {
            return $parentResult;
        }

        if (!\IPS\Settings::i()->spamtroll_check_registrations) {
            return $parentResult;
        }

        $ip = self::requestIp();
        $decision = static::scanRegistration($member, $emailAddress, $ip);

        if ($decision->scanned()) {
            static::scanner()->recorder()->record(
                $decision,
                $member->member_id ?: null,
                'registration',
                null,
                $ip,
                'Username: ' . $member->name . ', Email: ' . ($emailAddress ?: ($member->email ?: 'N/A')),
            );
        }

        return RegistrationAction::apply($member, $decision, $parentResult);
    }

    /**
     * Hide a comment, and the topic with it when the comment is the first
     * post.
     *
     * `Content::hide()` sets the column on the comment and saves; it does not
     * touch the item (docs/SUITE-FACTS.md, U8). Hiding a spam first post
     * therefore used to leave the topic — with its title, which is where the
     * spam usually is — visible to guests and to search engines.
     *
     * `FALSE`, not `NULL`: null credits the hide to the currently logged-in
     * member, which during a post is the spammer (U8b).
     *
     * @param mixed $comment
     * @param mixed $item
     */
    private static function hide($comment, $item, bool $first): void
    {
        self::hideOne($comment, 'comment');

        if ($first) {
            self::hideOne($item, 'item');
        }
    }

    /**
     * @param mixed $target
     */
    private static function hideOne($target, string $what): void
    {
        if (!\is_object($target) || !method_exists($target, 'hide')) {
            Recorder::note('hide', new \RuntimeException(
                'Cannot hide ' . $what . ': ' . (\is_object($target) ? $target::class : \gettype($target)) . ' has no hide()',
            ));

            return;
        }

        try {
            $target->hide(false);
        } catch (\Throwable $t) {
            /* hide() throws when the class maps neither a `hidden` nor an
             * `approved` column (U8). Worth a line in the log: the verdict
             * was reached and then not acted on, which is exactly the case an
             * admin would otherwise never find out about. */
            Recorder::note('hide/' . $what, $t);
        }
    }

    private static function requestIp(): ?string
    {
        try {
            $ip = \IPS\Request::i()->ipAddress();

            return $ip !== '' ? $ip : null;
        } catch (\Throwable $t) {
            return null;
        }
    }

    /**
     * @param mixed $result
     */
    private static function contentId($result): ?int
    {
        if (!\is_object($result)) {
            return null;
        }

        foreach (['pid', 'id'] as $property) {
            if (isset($result->{$property}) && is_numeric($result->{$property})) {
                return (int) $result->{$property};
            }
        }

        return null;
    }
}
