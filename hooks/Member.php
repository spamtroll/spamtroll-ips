//<?php

/* To prevent PHP errors (extending class does not exist) revealing path */
if (!\defined('\IPS\SUITE_UNIQUE_KEY')) {
    exit;
}

/**
 * Hook on \IPS\Member::spamService — the registration spam check.
 *
 * An adapter and nothing more; see hooks/Comment.php for why. Note that the
 * Suite only calls spamService() when its own spam defence is switched on
 * (docs/SUITE-FACTS.md, U4b), so this hook can be installed and enabled and
 * still never run. The AdminCP dashboard says so when that is the case.
 */
abstract class spamtroll_hook_Member extends _HOOK_CLASS_
{
    /**
     * @param mixed $type
     * @param mixed $emailAddress
     * @param mixed $spamCode
     * @param mixed $disposable
     * @param mixed $geoBlock
     *
     * @return int|null
     */
    public function spamService($type = 'register', $emailAddress = NULL, &$spamCode = NULL, &$disposable = FALSE, &$geoBlock = FALSE)
    {
        $result = parent::spamService($type, $emailAddress, $spamCode, $disposable, $geoBlock);

        return \IPS\spamtroll\Scanner\Gateway::applyToRegistration($this, $type, $emailAddress, $result);
    }
}
