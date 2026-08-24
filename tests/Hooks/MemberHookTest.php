<?php

declare(strict_types=1);

use IPS\spamtroll\Scanner\RegistrationAction;
use IPS\spamtroll\Tests\Support\FakeHttpClient;
use IPS\spamtroll\Tests\Support\FakeMemberParent;
use IPS\spamtroll\Tests\Support\HookHarness;
use IPS\spamtroll\Tests\Support\ThrowingSettings;
use Spamtroll\Sdk\Response\CheckSpamResponse;

/**
 * hooks/Member.php over an instrumented \IPS\Member.
 *
 * The registration path is where the old code was fail-closed: its inner
 * catch took SpamtrollException only, so a \Error — which is what a package
 * shipped without vendor/ produces — escaped past the outer
 * catch (\RuntimeException) and out of spamService() entirely. Nobody could
 * register.
 *
 * It is also where two thirds of the action matrix did nothing. See
 * sources/Scanner/RegistrationAction.php for why returning a code was never
 * going to work.
 */

beforeEach(function (): void {
    $this->hook = HookHarness::compile('Member', FakeMemberParent::class);
});

function registeringMember(string $hookClass, string $name = 'spammer', string $email = 'spam@example.test'): object
{
    $member = new $hookClass();
    $member->name = $name;
    $member->email = $email;

    return $member;
}

it('calls the parent exactly once and returns its answer when the content is clean', function (): void {
    scannerOver(FakeHttpClient::json(200, scanBody(CheckSpamResponse::STATUS_SAFE, 1.0)));
    FakeMemberParent::$result = 1;

    $member = registeringMember($this->hook, 'alice', 'alice@example.test');
    $result = $member->spamService();

    expect(FakeMemberParent::$callCount)->toBe(1);
    expect($result)->toBe(1);
});

it('lets the registration through when reading a setting throws an Error', function (): void {
    /* The original defect: no vendor/, so \IPS\spamtroll\Application blew up
     * with a \Error and registration was dead for everyone. */
    $member = registeringMember($this->hook);
    \IPS\Settings::setInstance(new ThrowingSettings());
    FakeMemberParent::$result = 1;

    $result = $member->spamService();

    expect(FakeMemberParent::$callCount)->toBe(1);
    expect($result)->toBe(1);
});

it('lets the registration through when the scan fails', function (): void {
    scannerOver(FakeHttpClient::throwing(new RuntimeException('network is down')));
    FakeMemberParent::$result = null;

    $result = registeringMember($this->hook)->spamService();

    expect(FakeMemberParent::$callCount)->toBe(1);
    expect($result)->toBeNull();
});

it('does not call the parent a second time when the parent itself throws', function (): void {
    scannerOver(FakeHttpClient::json(200, scanBody(CheckSpamResponse::STATUS_SAFE, 1.0)));
    FakeMemberParent::$throw = new RuntimeException('spam service unreachable');

    $member = registeringMember($this->hook);

    expect(fn () => $member->spamService())->toThrow(RuntimeException::class, 'spam service unreachable');
    expect(FakeMemberParent::$callCount)->toBe(1);
});

it('denies a blocked registration with the only code the Suite acts on', function (): void {
    scannerOver(FakeHttpClient::json(200, scanBody(CheckSpamResponse::STATUS_BLOCKED, 16.0)));
    FakeMemberParent::$result = 1;

    $result = registeringMember($this->hook)->spamService();

    expect($result)->toBe(RegistrationAction::DENY);
});

it('puts a moderated registration on post moderation instead of returning a code nobody reads', function (): void {
    scannerOver(FakeHttpClient::json(200, scanBody(CheckSpamResponse::STATUS_SUSPICIOUS, 8.0)));
    FakeMemberParent::$result = 1;

    $member = registeringMember($this->hook);
    $result = $member->spamService();

    expect($member->mod_posts)->toBe(-1);
    expect($result)->toBe(RegistrationAction::PROCEED);
});

it('requires administrator approval for a warned registration', function (): void {
    /* `warn` only comes out of the legacy threshold branch, where the
     * suspicious action is configurable. Raw 15.0 normalises to 0.5, which
     * sits between the two thresholds. */
    settings()->spamtroll_override_thresholds = true;
    settings()->spamtroll_action_suspicious = 'warn';
    scannerOver(FakeHttpClient::json(200, scanBody(CheckSpamResponse::STATUS_SAFE, 15.0)));
    FakeMemberParent::$result = 1;

    $member = registeringMember($this->hook);
    $result = $member->spamService();

    expect(settings()->reg_auth_type)->toBe('admin');
    expect($member->mod_posts)->toBe(0);
    expect($result)->toBe(RegistrationAction::PROCEED);
});

it('keeps another spam service\'s denial', function (): void {
    scannerOver(FakeHttpClient::json(200, scanBody(CheckSpamResponse::STATUS_SAFE, 1.0)));
    FakeMemberParent::$result = RegistrationAction::DENY;

    $result = registeringMember($this->hook)->spamService();

    expect($result)->toBe(RegistrationAction::DENY);
});

it('does not touch the member when the verdict is allow', function (): void {
    scannerOver(FakeHttpClient::json(200, scanBody(CheckSpamResponse::STATUS_SAFE, 1.0)));
    FakeMemberParent::$result = 1;

    $member = registeringMember($this->hook);
    $member->spamService();

    expect($member->mod_posts)->toBe(0);
    expect(settings()->reg_auth_type)->toBe('none');
});

it('records the registration scan', function (): void {
    $scanner = scannerOver(FakeHttpClient::json(200, scanBody(CheckSpamResponse::STATUS_BLOCKED, 16.0)));
    FakeMemberParent::$result = 1;

    registeringMember($this->hook, 'spammer', 'spam@example.test')->spamService();

    $rows = recorderOf($scanner)->rows;
    expect($rows)->toHaveCount(1);
    expect($rows[0]['contentType'])->toBe('registration');
    expect($rows[0]['preview'])->toBe('Username: spammer, Email: spam@example.test');
});

it('records the address hash so deleting the account also deletes the scan', function (): void {
    /* A registration is scanned before the account exists, so its row has no
     * member id and MemberSync's delete-by-member-id never reached it. */
    $scanner = scannerOver(FakeHttpClient::json(200, scanBody(CheckSpamResponse::STATUS_BLOCKED, 16.0)));
    FakeMemberParent::$result = 1;

    registeringMember($this->hook, 'spammer', 'spam@example.test')->spamService();

    expect(recorderOf($scanner)->rows[0]['email'])->toBe('spam@example.test');
});

it('does nothing when registration scanning is switched off', function (): void {
    $http = FakeHttpClient::json(200, scanBody(CheckSpamResponse::STATUS_BLOCKED, 16.0));
    scannerOver($http);
    settings()->spamtroll_check_registrations = false;
    FakeMemberParent::$result = 1;

    $result = registeringMember($this->hook)->spamService();

    expect($http->callCount)->toBe(0);
    expect($result)->toBe(1);
});
