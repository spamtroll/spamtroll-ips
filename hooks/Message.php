//<?php

/* To prevent PHP errors (extending class does not exist) revealing path */
if (!\defined('\IPS\SUITE_UNIQUE_KEY')) {
    exit;
}

/**
 * Hook for private messages spam checking
 */
abstract class spamtroll_hook_Message extends _HOOK_CLASS_
{
    /**
     * Process after create
     *
     * @param \IPS\Content\Comment|null $comment Comment
     * @param array                     $values  Values
     * @return void
     */
    public function processAfterCreate($comment, $values)
    {
        try {
            parent::processAfterCreate($comment, $values);

            // Check if spam checking is enabled for messages
            if (!\IPS\spamtroll\Application::isEnabled()) {
                return;
            }

            if (!\IPS\Settings::i()->spamtroll_check_messages) {
                return;
            }

            // Get the member
            $member = $this->author();

            // Check if member should bypass
            if (\IPS\spamtroll\Application::shouldBypass($member)) {
                return;
            }

            // Get content text
            $content = strip_tags($this->content());
            if (empty(trim($content))) {
                return;
            }

            // Get IP address
            $ipAddress = \IPS\Request::i()->ipAddress();

            // Call API
            try {
                $client = \IPS\spamtroll\Application::apiClient();
                $response = $client->checkSpam($content, 'message', $ipAddress);

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
                    'message',
                    $this->id ?? null,
                    $ipAddress,
                    $status,
                    $spamScore,
                    $response->getSymbols(),
                    $response->getThreatCategories(),
                    $action,
                    mb_substr($content, 0, 500)
                );

                // Execute action
                $this->executeMessageSpamAction($action, $member, $spamScore);

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
     * Execute spam action on message
     *
     * @param string      $action Action to take
     * @param \IPS\Member $member Member
     * @param float       $score  Spam score
     * @return void
     */
    protected function executeMessageSpamAction(string $action, \IPS\Member $member, float $score): void
    {
        switch ($action) {
            case 'block':
                // Delete the message
                try {
                    $this->delete();
                } catch (\Exception $e) {
                    \IPS\Log::log('Failed to delete spam message: ' . $e->getMessage(), 'spamtroll');
                }
                // Notify admins
                $this->notifyMessageAdmins($member, $score, 'blocked');
                break;

            case 'moderate':
                // For messages, we can't really moderate, so log it
                $this->notifyMessageAdmins($member, $score, 'moderated');
                break;

            case 'warn':
                // Log warning
                $this->notifyMessageAdmins($member, $score, 'warning');
                break;

            case 'allow':
            default:
                // Do nothing
                break;
        }
    }

    /**
     * Notify admins about spam message detection
     *
     * @param \IPS\Member $member Member
     * @param float       $score  Spam score
     * @param string      $type   Notification type
     * @return void
     */
    protected function notifyMessageAdmins(\IPS\Member $member, float $score, string $type): void
    {
        try {
            \IPS\Log::log(
                sprintf(
                    'Spamtroll %s: Message by %s (ID: %d) with score %.2f',
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
