<?php

declare(strict_types=1);
/**
 * @brief       Spamtroll Anti-Spam Upgrade Step — 1.0.1
 *
 * @package     IPS Community Suite
 * @subpackage  Spamtroll Anti-Spam
 *
 * 1.0.1 only swaps the in-tree API client for the shared
 * spamtroll/php-sdk Composer package. No schema / settings changes,
 * so this handler is a no-op — IPS still needs the file to exist so
 * the installer accepts the upload.
 */

namespace IPS\spamtroll\setup\upg_10001;

class _Upgrade
{
    public function step1($data)
    {
        return true;
    }

    public function step1CustomTitle()
    {
        return 'Upgrading Spamtroll Anti-Spam to 1.0.1';
    }
}
