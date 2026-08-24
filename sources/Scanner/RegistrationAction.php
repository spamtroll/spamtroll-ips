<?php

declare(strict_types=1);
/**
 * @brief       Spamtroll registration actions
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

/**
 * Applies a registration verdict to the member.
 *
 * The previous version returned an IPS spam-service code and assumed someone
 * downstream would act on it: 2 for "moderate", 3 for "review", 4 for
 * "block". Two of those did nothing whatsoever.
 *
 * The Suite performs the side effects for codes 2, 3 and 5 *inside*
 * `spamService()`, in a switch driven by the `spam_service_action_{$code}`
 * settings — a branch a hook that returns a code never reaches. Its caller
 * then tests `$spamAction == 4` and nothing else (docs/SUITE-FACTS.md, U4c).
 * So "block" worked, while "moderate" and "warn" let the registration through
 * untouched, leaving no trace beyond a log line. The comment in the old code
 * ("1 = not spam, 2 = moderate, 3 = review, 4 = block") described a
 * convention that does not exist.
 *
 * The fix is to stop signalling and start doing: set the property the Suite
 * would have set, then return a code that does not undo it. The member is
 * saved right after `spamService()` returns (U4d), so the property sticks.
 */
final class _RegistrationAction
{
    /** The only code `register.php` reacts to. */
    public const DENY = 4;

    /** "Carry on" in the Suite's own convention. */
    public const PROCEED = 1;

    /**
     * @param mixed $parentResult What the framework's own spam service decided
     *
     * @return mixed
     */
    public static function apply(\IPS\Member $member, Decision $decision, $parentResult)
    {
        /* A verdict from another spam service that already denied the
         * registration outranks anything we would do. */
        if ($parentResult === self::DENY) {
            return self::DENY;
        }

        switch ($decision->action) {
            case Decision::ACTION_BLOCK:
                return self::DENY;

            case Decision::ACTION_MODERATE:
                /* Code 5's effect: every post this member makes waits for a
                 * moderator, indefinitely. */
                self::set($member, 'mod_posts', -1);

                return self::PROCEED;

            case Decision::ACTION_WARN:
                /* Code 2's effect: the account needs an administrator to
                 * approve it before it can be used. */
                self::setRegistrationApproval();

                return self::PROCEED;

            default:
                return $parentResult;
        }
    }

    /**
     * @param mixed $value
     */
    private static function set(\IPS\Member $member, string $property, $value): void
    {
        try {
            $member->{$property} = $value;
        } catch (\Throwable $t) {
            Recorder::note('registration action', $t);
        }
    }

    private static function setRegistrationApproval(): void
    {
        try {
            \IPS\Settings::i()->reg_auth_type = 'admin';
        } catch (\Throwable $t) {
            Recorder::note('registration action', $t);
        }
    }
}
