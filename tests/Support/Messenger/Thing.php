<?php

declare(strict_types=1);

namespace IPS\spamtroll\Tests\Support\Messenger;

/**
 * A third-party content class whose namespace happens to contain the word
 * "Messenger".
 *
 * The old skip test was `strpos($itemClass, 'Messenger') !== false`, so
 * anything shaped like this was silently excluded from scanning — an
 * application could disable spam checking for its own content by accident,
 * just by picking a namespace.
 */
class Thing
{
    public int $id = 0;
}
