//<?php

/* To prevent PHP errors (extending class does not exist) revealing path */
if (!\defined('\IPS\SUITE_UNIQUE_KEY')) {
    exit;
}

/**
 * Hook for member registration spam checking
 */
abstract class spamtroll_hook_Member extends _HOOK_CLASS_
{
    /**
     * Check for spam on registration
     *
     * @return bool|null
     */
    public function spamService()
    {
        try {
            $result = parent::spamService();

            // Check if spam checking is enabled for registrations
            if (!\IPS\spamtroll\Application::isEnabled()) {
                return $result;
            }

            if (!\IPS\Settings::i()->spamtroll_check_registrations) {
                return $result;
            }

            // Get IP address
            $ipAddress = \IPS\Request::i()->ipAddress();

            // Build content to check
            $content = $this->name;
            if ($this->email) {
                $content .= ' ' . $this->email;
            }

            // Call API
            try {
                $client = \IPS\spamtroll\Application::apiClient();
                $response = $client->checkSpam(
                    $content,
                    'registration',
                    $ipAddress,
                    $this->name,
                    $this->email
                );

                if (!$response->success) {
                    \IPS\Log::log('Spamtroll API error: ' . $response->error, 'spamtroll');
                    return $result;
                }

                $spamScore = $response->getSpamScore();
                $status = \IPS\spamtroll\Application::determineStatus($spamScore);
                $action = \IPS\spamtroll\Application::determineAction($spamScore);

                // Log the result
                \IPS\spamtroll\Application::log(
                    null,
                    'registration',
                    null,
                    $ipAddress,
                    $status,
                    $spamScore,
                    $response->getSymbols(),
                    $response->getThreatCategories(),
                    $action,
                    'Username: ' . $this->name . ', Email: ' . ($this->email ?: 'N/A')
                );

                // Execute action based on spam score
                return $this->executeRegistrationSpamAction($action, $spamScore, $result);

            } catch (\IPS\spamtroll\Api\Exception $e) {
                \IPS\Log::log('Spamtroll API exception: ' . $e->getMessage(), 'spamtroll');
                return $result;
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
     * Execute spam action on registration
     *
     * @param string    $action Action to take
     * @param float     $score  Spam score
     * @param bool|null $parentResult Parent method result
     * @return bool|null
     */
    protected function executeRegistrationSpamAction(string $action, float $score, $parentResult)
    {
        // Log the action
        \IPS\Log::log(
            sprintf(
                'Spamtroll registration check: %s (score: %.2f) for %s',
                $action,
                $score,
                $this->name
            ),
            'spamtroll'
        );

        switch ($action) {
            case 'block':
                // Return 4 to indicate spam (IPS spam service convention)
                // 1 = not spam, 2 = moderate, 3 = review, 4 = block
                return 4;

            case 'moderate':
                // Return 2 to put in moderation
                return $parentResult === 4 ? 4 : 2;

            case 'warn':
                // Return 3 for review
                return $parentResult === 4 ? 4 : ($parentResult === 2 ? 2 : 3);

            case 'allow':
            default:
                // Keep parent result
                return $parentResult;
        }
    }

    /**
     * Additional spam check during save
     *
     * @return void
     */
    public function save()
    {
        try {
            // Only check on new registrations
            if ($this->member_id === null && \IPS\spamtroll\Application::isEnabled()) {
                // The spamService() method will be called by IPS during registration
            }

            parent::save();
        } catch (\RuntimeException $e) {
            if (method_exists(get_parent_class(), __FUNCTION__)) {
                return \call_user_func_array('parent::' . __FUNCTION__, \func_get_args());
            } else {
                throw $e;
            }
        }
    }
}
