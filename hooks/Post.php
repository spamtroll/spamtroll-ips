//<?php

/* To prevent PHP errors (extending class does not exist) revealing path */
if (!\defined('\IPS\SUITE_UNIQUE_KEY')) {
    exit;
}

/**
 * Hook for forum posts spam checking
 */
abstract class spamtroll_hook_Post extends _HOOK_CLASS_
{
    /**
     * Process after content is created
     *
     * @param \IPS\Content\Comment|null $comment  Comment object
     * @param array                     $values   Form values
     * @return void
     */
    public function processAfterCreate($comment, $values)
    {
        try {
            parent::processAfterCreate($comment, $values);

            // Check if spam checking is enabled for posts
            if (!\IPS\spamtroll\Application::isEnabled()) {
                return;
            }

            if (!\IPS\Settings::i()->spamtroll_check_posts) {
                return;
            }

            // Get the content to check (either comment or this post)
            $contentToCheck = $comment ?: $this;

            // Get the member
            $member = $contentToCheck->author();

            // Check if member should bypass
            if (\IPS\spamtroll\Application::shouldBypass($member)) {
                return;
            }

            // Get content text
            $content = strip_tags($contentToCheck->content());
            if (empty(trim($content))) {
                return;
            }

            // Get IP address
            $ipAddress = \IPS\Request::i()->ipAddress();

            // Call API
            try {
                $client = \IPS\spamtroll\Application::apiClient();
                $response = $client->checkSpam($content, 'forum', $ipAddress);

                if (!$response->success) {
                    \IPS\Log::log('Spamtroll API error: ' . $response->error, 'spamtroll');
                    return;
                }

                $spamScore = $response->getSpamScore();
                $status = \IPS\spamtroll\Application::determineStatus($spamScore);
                $action = \IPS\spamtroll\Application::determineAction($spamScore);

                // Log the result
                \IPS\spamtroll\Application::log(
                    $member->member_id,
                    'post',
                    $contentToCheck->pid ?? $contentToCheck->id ?? null,
                    $ipAddress,
                    $status,
                    $spamScore,
                    $response->getSymbols(),
                    $response->getThreatCategories(),
                    $action,
                    mb_substr($content, 0, 500)
                );

                // Execute action
                $this->executeSpamAction($action, $contentToCheck, $member, $spamScore);

            } catch (\IPS\spamtroll\Api\Exception $e) {
                \IPS\Log::log('Spamtroll API exception: ' . $e->getMessage(), 'spamtroll');
            }
        } catch (\RuntimeException $e) {
            if (method_exists(get_parent_class(), __FUNCTION__)) {
                return \call_user_func_array('parent::' . __FUNCTION__, \func_get_args());
            } else {
                throw $e;
            }
        }
    }

    /**
     * Execute spam action on content
     *
     * @param string                 $action   Action to take
     * @param \IPS\Content\Comment   $content  Content object
     * @param \IPS\Member            $member   Member
     * @param float                  $score    Spam score
     * @return void
     */
    protected function executeSpamAction(string $action, $content, \IPS\Member $member, float $score): void
    {
        switch ($action) {
            case 'block':
                // Hide/delete the content
                if (method_exists($content, 'hide')) {
                    $content->hide(null);
                }
                // Send notification to admins
                $this->notifyAdmins($content, $member, $score, 'blocked');
                break;

            case 'moderate':
                // Put content in moderation queue
                if (method_exists($content, 'hide')) {
                    $content->hide(null);
                }
                break;

            case 'warn':
                // Log warning but allow content
                $this->notifyAdmins($content, $member, $score, 'warning');
                break;

            case 'allow':
            default:
                // Do nothing
                break;
        }
    }

    /**
     * Notify admins about spam detection
     *
     * @param \IPS\Content\Comment $content Content
     * @param \IPS\Member          $member  Member
     * @param float                $score   Spam score
     * @param string               $type    Notification type
     * @return void
     */
    protected function notifyAdmins($content, \IPS\Member $member, float $score, string $type): void
    {
        try {
            \IPS\Log::log(
                sprintf(
                    'Spamtroll %s: Post by %s (ID: %d) with score %.2f',
                    $type,
                    $member->name,
                    $member->member_id,
                    $score
                ),
                'spamtroll'
            );
        } catch (\Exception $e) {
            // Ignore logging errors
        }
    }
}
