<?php

namespace Stasadev\SlackNotifier\Tests\Support;

class StringableValue
{
    public function __toString(): string
    {
        return 'stringable-value';
    }
}
