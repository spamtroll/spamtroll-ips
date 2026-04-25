//<?php

/* To prevent PHP errors (extending class does not exist) revealing path */
if (!\defined('\IPS\SUITE_UNIQUE_KEY')) {
    exit;
}

/**
 * Hook on \IPS\Content\Comment::create — intercepts every post reply
 * and (via Item::processAfterCreate → Comment::create) the first post
 * of each new topic.
 *
 * Skips private messages: the Messenger Conversation path also flows
 * through Comment::create, but as of 1.0.2 we explicitly bail out for
 * those item classes. Replaces the old per-class hooks on
 * \IPS\forums\Topic\Post and \IPS\core\Messenger\Message, which only
 * intercepted Item-level processAfterCreate and therefore missed
 * ordinary replies.
 */
abstract class spamtroll_hook_Comment extends _HOOK_CLASS_
{
    public static function create(
        $item, $comment, $first = FALSE, $guestName = NULL,
        $incrementPostCount = NULL, $member = NULL,
        \IPS\DateTime $time = NULL, $ipAddress = NULL,
        $hiddenStatus = NULL, $anonymous = NULL
    ) {
        try {
            /* Create the comment first — we never block at the DB level. The
             * spam check runs afterwards and hides / moderates / leaves alone
             * based on the policy. Doing it this way matches IPS's own
             * antispam flow and avoids breaking callers that expect a
             * created object back. */
            $result = parent::create(
                $item, $comment, $first, $guestName,
                $incrementPostCount, $member, $time,
                $ipAddress, $hiddenStatus, $anonymous
            );

            if (!\IPS\spamtroll\Application::isEnabled()) {
                return $result;
            }

            /* Skip private messages outright. Messenger Conversations also
             * route through Comment::create, but we don't scan them anymore. */
            $itemClass = is_object($item) ? get_class($item) : '';
            if ($itemClass === 'IPS\\core\\Messenger\\Conversation'
                || strpos($itemClass, 'Messenger') !== false
            ) {
                return $result;
            }

            if (!\IPS\Settings::i()->spamtroll_check_posts) {
                return $result;
            }

            $postingMember = $member ?: \IPS\Member::loggedIn();
            if (\IPS\spamtroll\Application::shouldBypass($postingMember)) {
                return $result;
            }

            $text = trim(strip_tags((string) $comment));
            if ($text === '') {
                return $result;
            }

            $ip = $ipAddress ?: \IPS\Request::i()->ipAddress();

            try {
                $client = \IPS\spamtroll\Application::apiClient();
                $response = $client->checkSpam(new \Spamtroll\Sdk\Request\CheckSpamRequest(
                    $text,
                    \Spamtroll\Sdk\Request\CheckSpamRequest::SOURCE_FORUM,
                    $ip,
                    $postingMember->name ?: null,
                    $postingMember->email ?: null
                ));

                if (!$response->success) {
                    \IPS\Log::log('Spamtroll API error: ' . ($response->error ?? '(none)'), 'spamtroll');
                    return $result;
                }

                $score  = $response->getSpamScore();
                $status = \IPS\spamtroll\Application::determineStatus($score);
                $action = \IPS\spamtroll\Application::determineAction($score);

                \IPS\spamtroll\Application::log(
                    $postingMember->member_id ?: null,
                    'post',
                    is_object($result) && isset($result->pid) ? $result->pid
                        : (is_object($result) && isset($result->id) ? $result->id : null),
                    $ip,
                    $status,
                    $score,
                    $response->getSymbols(),
                    $response->getThreatCategories(),
                    $action,
                    mb_substr($text, 0, 500),
                    $response->getSubmissionId()
                );

                /* Apply the action to the newly-created comment. */
                if (is_object($result)) {
                    if ($action === 'block' || $action === 'moderate') {
                        if (method_exists($result, 'hide')) {
                            $result->hide(null);
                        }
                    }
                }
            } catch (\Spamtroll\Sdk\Exception\SpamtrollException $e) {
                \IPS\Log::log('Spamtroll API exception: ' . $e->getMessage(), 'spamtroll');
            } catch (\Throwable $t) {
                \IPS\Log::log('Spamtroll hook error: ' . $t->getMessage(), 'spamtroll');
            }

            return $result;
        } catch (\RuntimeException $e) {
            if (method_exists(get_parent_class(), __FUNCTION__)) {
                return \call_user_func_array('parent::' . __FUNCTION__, \func_get_args());
            } else {
                throw $e;
            }
        }
    }
}
