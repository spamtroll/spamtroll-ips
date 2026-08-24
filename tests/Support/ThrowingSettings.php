<?php

declare(strict_types=1);

namespace IPS\spamtroll\Tests\Support;

/**
 * A settings object where reading anything blows up.
 *
 * This is how a package shipped without its vendor/ directory, a datastore
 * that lost its connection mid-request, or a framework upgrade that renamed a
 * property all look from inside a hook: a `\Error`, not an exception any
 * `catch (\Exception)` will see.
 *
 * Unsetting the declared properties in the constructor is what routes access
 * through __get; PHP only consults the magic method for properties that are
 * not present on the object.
 */
final class ThrowingSettings extends \IPS\Settings
{
    public function __construct()
    {
        foreach (get_object_vars($this) as $property => $ignored) {
            unset($this->{$property});
        }
    }

    public function __get(string $name): mixed
    {
        throw new \Error('Class "Spamtroll\\Sdk\\Client" not found');
    }

    public function __isset(string $name): bool
    {
        return true;
    }
}
