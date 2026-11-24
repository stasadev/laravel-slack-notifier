<?php

namespace Stasadev\SlackNotifier\Exceptions;

use RuntimeException;

class FormatterClassDoesNotExist extends RuntimeException implements SlackNotifierException
{
    public static function make(?string $name): self
    {
        return new self("The formatter class `{$name}` does not exist. Make sure you specify a valid class name in the `formatter` key of the slack-notifier.php config file.");
    }
}
