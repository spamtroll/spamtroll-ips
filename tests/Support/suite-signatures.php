<?php

declare(strict_types=1);

/**
 * Signatures of the IPS methods this application overrides, recorded from the
 * Suite's own source.
 *
 * A hook whose signature drifts from its parent's is a fatal error on every
 * page of the forum. That drift would otherwise be found by a user whose site
 * had just stopped working, after a minor Suite upgrade — so it is recorded
 * here, with the version it came from, and asserted in
 * tests/Unit/SuiteSignatureTest.php.
 *
 * @suite 4.7.22
 *
 * Sources (read-only; no Suite code is reproduced, only the parameter shapes
 * needed for interoperability):
 *   \IPS\Content\Comment::create  — system/Content/Comment.php:68
 *   \IPS\Member::spamService      — system/Member/Member.php:4038
 */

return [
    'suite' => '4.7.22',
    'recorded' => '2026-08-24',

    'IPS\\Content\\Comment::create' => [
        'static' => true,
        'parameters' => [
            ['name' => 'item', 'type' => null, 'optional' => false, 'byReference' => false],
            ['name' => 'comment', 'type' => null, 'optional' => false, 'byReference' => false],
            ['name' => 'first', 'type' => null, 'optional' => true, 'byReference' => false],
            ['name' => 'guestName', 'type' => null, 'optional' => true, 'byReference' => false],
            ['name' => 'incrementPostCount', 'type' => null, 'optional' => true, 'byReference' => false],
            ['name' => 'member', 'type' => null, 'optional' => true, 'byReference' => false],
            ['name' => 'time', 'type' => '?IPS\\DateTime', 'optional' => true, 'byReference' => false],
            ['name' => 'ipAddress', 'type' => null, 'optional' => true, 'byReference' => false],
            ['name' => 'hiddenStatus', 'type' => null, 'optional' => true, 'byReference' => false],
            ['name' => 'anonymous', 'type' => null, 'optional' => true, 'byReference' => false],
        ],
    ],

    'IPS\\Member::spamService' => [
        'static' => false,
        'parameters' => [
            ['name' => 'type', 'type' => null, 'optional' => true, 'byReference' => false],
            ['name' => 'emailAddress', 'type' => null, 'optional' => true, 'byReference' => false],
            ['name' => 'spamCode', 'type' => null, 'optional' => true, 'byReference' => true],
            ['name' => 'disposable', 'type' => null, 'optional' => true, 'byReference' => true],
            ['name' => 'geoBlock', 'type' => null, 'optional' => true, 'byReference' => true],
        ],
    ],
];
