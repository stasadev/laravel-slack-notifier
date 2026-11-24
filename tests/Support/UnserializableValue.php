<?php

namespace Stasadev\SlackNotifier\Tests\Support;

use JsonSerializable;
use RuntimeException;

/**
 * A value that Monolog cannot normalize, as jsonSerialize() throws.
 */
class UnserializableValue implements JsonSerializable
{
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        throw new RuntimeException('Cannot serialize this value.');
    }
}
