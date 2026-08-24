<?php

declare(strict_types=1);

use IPS\spamtroll\Tests\Support\FakeCommentParent;
use IPS\spamtroll\Tests\Support\FakeMemberParent;
use IPS\spamtroll\Tests\Support\HookHarness;
use IPS\spamtroll\Tests\Support\HookTransform;

/**
 * The hooks against the Suite's own signatures.
 *
 * A hook whose signature no longer matches its parent is a fatal error on
 * every page of the forum. Without this the drift would be found by a user
 * whose site had just stopped working after a minor Suite upgrade; with it,
 * it is found when the recorded signatures are refreshed for a new version.
 *
 * The comparison is by reflection, not by text: `\IPS\DateTime $time=NULL`
 * and `?\IPS\DateTime $time=NULL` are the same type, and only the second is
 * free of a PHP 8.4 deprecation.
 */

/** @return array<string, mixed> */
function suiteSignatures(): array
{
    /** @var array<string, mixed> $signatures */
    $signatures = require \dirname(__DIR__) . '/Support/suite-signatures.php';

    return $signatures;
}

/** @return array<int, array{name: string, type: string|null, optional: bool, byReference: bool}> */
function describeParameters(ReflectionMethod $method): array
{
    return array_map(static function (ReflectionParameter $parameter): array {
        $type = $parameter->getType();

        return [
            'name' => $parameter->getName(),
            'type' => $type === null ? null : ($type->allowsNull() && $type instanceof ReflectionNamedType && !$type->isBuiltin()
                ? '?' . $type->getName()
                : (string) $type),
            'optional' => $parameter->isOptional(),
            'byReference' => $parameter->isPassedByReference(),
        ];
    }, $method->getParameters());
}

it('records the Suite version the signatures came from', function (): void {
    $signatures = suiteSignatures();

    expect($signatures['suite'])->toBe('4.7.22');
});

it('keeps the comment hook in step with \IPS\Content\Comment::create', function (): void {
    $expected = suiteSignatures()['IPS\\Content\\Comment::create'];
    $hook = HookHarness::compile('Comment', FakeCommentParent::class);
    $method = new ReflectionMethod($hook, 'create');

    expect($method->isStatic())->toBe($expected['static']);
    expect(describeParameters($method))->toBe($expected['parameters']);
});

it('keeps the member hook in step with \IPS\Member::spamService', function (): void {
    $expected = suiteSignatures()['IPS\\Member::spamService'];
    $hook = HookHarness::compile('Member', FakeMemberParent::class);
    $method = new ReflectionMethod($hook, 'spamService');

    expect($method->isStatic())->toBe($expected['static']);
    expect(describeParameters($method))->toBe($expected['parameters']);
});

it('keeps the instrumented parents in step too', function (): void {
    $signatures = suiteSignatures();

    expect(describeParameters(new ReflectionMethod(FakeCommentParent::class, 'create')))
        ->toBe($signatures['IPS\\Content\\Comment::create']['parameters']);
    expect(describeParameters(new ReflectionMethod(FakeMemberParent::class, 'spamService')))
        ->toBe($signatures['IPS\\Member::spamService']['parameters']);
});

it('hooks the classes the signatures were taken from', function (): void {
    $declared = json_decode((string) file_get_contents(\dirname(__DIR__, 2) . '/data/hooks.json'), true);

    expect($declared['Comment']['class'])->toBe(HookTransform::MAP['Comment']['class']);
    expect($declared['Member']['class'])->toBe(HookTransform::MAP['Member']['class']);
});
