<?php

namespace Stasadev\SlackNotifier\Exceptions;

use RuntimeException;

class FormatterClassDoesNotExist extends RunTimeException
{
    public static function make(?string $name): self
    {
        return new self("The configured formatter class '{$name}' does not exist. Make sure you specific a valid class name in the 'formatter' key of the slack-notifier.php config file.");
    }
}
