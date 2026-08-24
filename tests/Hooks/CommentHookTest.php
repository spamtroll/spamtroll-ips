<?php

declare(strict_types=1);

use IPS\spamtroll\Scanner\Decision;
use IPS\spamtroll\Tests\Support\FakeCommentParent;
use IPS\spamtroll\Tests\Support\FakeHideable;
use IPS\spamtroll\Tests\Support\FakeHttpClient;
use IPS\spamtroll\Tests\Support\FakeUnhideable;
use IPS\spamtroll\Tests\Support\HookHarness;
use IPS\spamtroll\Tests\Support\Messenger\Thing;
use IPS\spamtroll\Tests\Support\ThrowingSettings;
use Spamtroll\Sdk\Response\CheckSpamResponse;

/**
 * hooks/Comment.php, compiled the way the Suite compiles it, over a parent
 * that counts its calls and throws on demand.
 *
 * The parent is instrumented rather than accommodating: it has no defensive
 * `method_exists()`, so a hook that stops matching the framework fails here
 * instead of passing on a stub's good manners.
 */

beforeEach(function (): void {
    $this->hook = HookHarness::compile('Comment', FakeCommentParent::class);
});

function createComment(string $hookClass, $item, $comment, $first = false, $member = null, $ipAddress = null)
{
    return $hookClass::create($item, $comment, $first, null, null, $member, null, $ipAddress, null, null);
}

it('calls the parent exactly once and returns what it returned', function (): void {
    scannerOver(FakeHttpClient::json(200, scanBody(CheckSpamResponse::STATUS_SAFE, 1.0)));
    FakeCommentParent::$result = new FakeHideable(42);

    $result = createComment($this->hook, new FakeHideable(1), 'hello there', false, member(), '203.0.113.7');

    expect(FakeCommentParent::$callCount)->toBe(1);
    expect($result)->toBe(FakeCommentParent::$result);
});

it('calls the parent exactly once when the scan fails', function (): void {
    scannerOver(FakeHttpClient::throwing(new RuntimeException('network is down')));
    FakeCommentParent::$result = new FakeHideable(42);

    createComment($this->hook, new FakeHideable(1), 'hello there', false, member(), '203.0.113.7');

    expect(FakeCommentParent::$callCount)->toBe(1);
});

it('calls the parent exactly once and survives when reading a setting throws an Error', function (): void {
    \IPS\Settings::setInstance(new ThrowingSettings());
    FakeCommentParent::$result = new FakeHideable(42);

    $result = createComment($this->hook, new FakeHideable(1), 'hello there', false, member(), '203.0.113.7');

    expect(FakeCommentParent::$callCount)->toBe(1);
    expect($result)->toBe(FakeCommentParent::$result);
});

it('does not call the parent a second time when the parent itself throws', function (): void {
    scannerOver(FakeHttpClient::json(200, scanBody(CheckSpamResponse::STATUS_SAFE, 1.0)));
    FakeCommentParent::$throw = new RuntimeException('the forum rejected this post');

    $call = fn () => createComment($this->hook, new FakeHideable(1), 'hello there', false, member(), '203.0.113.7');

    expect($call)->toThrow(RuntimeException::class, 'the forum rejected this post');
    expect(FakeCommentParent::$callCount)->toBe(1);
});

it('hides a blocked comment, crediting the hide to nobody', function (): void {
    scannerOver(FakeHttpClient::json(200, scanBody(CheckSpamResponse::STATUS_BLOCKED, 16.0)));
    $created = new FakeHideable(42);
    FakeCommentParent::$result = $created;

    createComment($this->hook, new FakeHideable(1), 'buy cheap watches', false, member(), '203.0.113.7');

    expect($created->hideCount)->toBe(1);
    expect($created->hiddenBy)->toBe([false]);
});

it('hides the topic as well when the blocked comment is the first post', function (): void {
    scannerOver(FakeHttpClient::json(200, scanBody(CheckSpamResponse::STATUS_BLOCKED, 16.0)));
    $topic = new FakeHideable(1);
    $created = new FakeHideable(42);
    FakeCommentParent::$result = $created;

    createComment($this->hook, $topic, 'buy cheap watches', true, member(), '203.0.113.7');

    expect($created->hideCount)->toBe(1);
    expect($topic->hideCount)->toBe(1);
});

it('leaves the topic alone when the blocked comment is a reply', function (): void {
    scannerOver(FakeHttpClient::json(200, scanBody(CheckSpamResponse::STATUS_BLOCKED, 16.0)));
    $topic = new FakeHideable(1);
    FakeCommentParent::$result = new FakeHideable(42);

    createComment($this->hook, $topic, 'buy cheap watches', false, member(), '203.0.113.7');

    expect($topic->hideCount)->toBe(0);
});

it('logs instead of going quiet when hide() throws', function (): void {
    scannerOver(FakeHttpClient::json(200, scanBody(CheckSpamResponse::STATUS_BLOCKED, 16.0)));
    $created = new FakeHideable(42);
    $created->hideThrows = new RuntimeException('no hidden column on this class');
    FakeCommentParent::$result = $created;

    createComment($this->hook, new FakeHideable(1), 'buy cheap watches', false, member(), '203.0.113.7');

    expect(FakeCommentParent::$callCount)->toBe(1);
    expect(loggedMessages())->toContain('Spamtroll hide/comment: no hidden column on this class');
});

it('logs instead of going quiet when the object has no hide() at all', function (): void {
    scannerOver(FakeHttpClient::json(200, scanBody(CheckSpamResponse::STATUS_BLOCKED, 16.0)));
    FakeCommentParent::$result = new FakeUnhideable(42);

    createComment($this->hook, new FakeHideable(1), 'buy cheap watches', false, member(), '203.0.113.7');

    $messages = implode("\n", loggedMessages());
    expect($messages)->toContain('Cannot hide comment');
    expect($messages)->toContain(FakeUnhideable::class);
});

it('skips private messages', function (): void {
    $http = FakeHttpClient::json(200, scanBody(CheckSpamResponse::STATUS_BLOCKED, 16.0));
    scannerOver($http);
    $created = new FakeHideable(42);
    FakeCommentParent::$result = $created;

    createComment($this->hook, new \IPS\core\Messenger\Conversation(), 'buy cheap watches', false, member(), '203.0.113.7');

    expect($http->callCount)->toBe(0);
    expect($created->hideCount)->toBe(0);
});

it('scans a third-party class that merely has Messenger in its namespace', function (): void {
    $http = FakeHttpClient::json(200, scanBody(CheckSpamResponse::STATUS_BLOCKED, 16.0));
    scannerOver($http);
    $created = new FakeHideable(42);
    FakeCommentParent::$result = $created;

    createComment($this->hook, new Thing(), 'buy cheap watches', false, member(), '203.0.113.7');

    expect($http->callCount)->toBe(1);
    expect($created->hideCount)->toBe(1);
});

it('records the scan against the created comment', function (): void {
    $scanner = scannerOver(FakeHttpClient::json(200, scanBody(CheckSpamResponse::STATUS_BLOCKED, 16.0)));
    FakeCommentParent::$result = new FakeHideable(42);

    createComment($this->hook, new FakeHideable(1), '<b>buy cheap watches</b>', false, member(7), '203.0.113.7');

    $rows = recorderOf($scanner)->rows;
    expect($rows)->toHaveCount(1);
    expect($rows[0]['contentType'])->toBe('post');
    expect($rows[0]['contentId'])->toBe(42);
    expect($rows[0]['memberId'])->toBe(7);
    expect($rows[0]['ip'])->toBe('203.0.113.7');
    expect($rows[0]['preview'])->toBe('buy cheap watches');
    expect($rows[0]['decision']->action)->toBe(Decision::ACTION_BLOCK);
});

it('does not scan a member who is allowed to bypass', function (): void {
    $http = FakeHttpClient::json(200, scanBody(CheckSpamResponse::STATUS_BLOCKED, 16.0));
    scannerOver($http);
    settings()->spamtroll_bypass_min_posts = 10;
    FakeCommentParent::$result = new FakeHideable(42);

    createComment($this->hook, new FakeHideable(1), 'buy cheap watches', false, member(1, 500), '203.0.113.7');

    expect($http->callCount)->toBe(0);
    expect(FakeCommentParent::$callCount)->toBe(1);
});
