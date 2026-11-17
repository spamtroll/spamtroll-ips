<?php

declare(strict_types=1);

namespace IPS\spamtroll\setup\upg_10005;

/** Apply repaired legacy migrations to forums already reporting 1.0.4. */
class _Upgrade
{
    public function step1($data = null)
    {
        require_once \dirname(__DIR__) . '/upg_10002/upgrade.php';
        require_once \dirname(__DIR__) . '/upg_10003/upgrade.php';

        (new \IPS\spamtroll\setup\upg_10002\_Upgrade())->step1();
        (new \IPS\spamtroll\setup\upg_10003\_Upgrade())->step1();

        return true;
    }

    public function step1CustomTitle()
    {
        return 'Upgrading Spamtroll Anti-Spam to 1.0.5';
    }
}
