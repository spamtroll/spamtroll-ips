//<?php

/* To prevent PHP errors (extending class does not exist) revealing path */
if (!\defined('\IPS\SUITE_UNIQUE_KEY')) {
    exit;
}

/**
 * Hook on \IPS\Content\Comment::create — intercepts every post reply and
 * (via Item::processAfterCreate -> Comment::create) the first post of each
 * new topic.
 *
 * An adapter and nothing more. parent::create() is called once, outside any
 * try, so there is no path on which it can run twice; everything else lives
 * behind \IPS\spamtroll\Scanner\Gateway, whose contract is that it does not
 * throw. That is the whole fail-open story for this file — there is nothing
 * here left to get wrong.
 */
abstract class spamtroll_hook_Comment extends _HOOK_CLASS_
{
    public static function create(
        $item, $comment, $first = FALSE, $guestName = NULL,
        $incrementPostCount = NULL, $member = NULL,
        ?\IPS\DateTime $time = NULL, $ipAddress = NULL,
        $hiddenStatus = NULL, $anonymous = NULL
    ) {
        /* Create the comment first — we never block at the DB level. The scan
         * runs afterwards and hides, moderates or leaves alone. */
        $result = parent::create(
            $item, $comment, $first, $guestName,
            $incrementPostCount, $member, $time,
            $ipAddress, $hiddenStatus, $anonymous
        );

        \IPS\spamtroll\Scanner\Gateway::applyToComment(
            $result, $item, $comment, $first, $member, $ipAddress
        );

        return $result;
    }
}
