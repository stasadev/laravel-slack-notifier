<?php

namespace Stasadev\SlackNotifier\Exceptions;

use RuntimeException;
use Throwable;

class WebhookSendFail extends RunTimeException
{
    public static function make(Throwable $exception): self
    {
        return new self(get_class($exception).': '.$exception->getMessage(), 1, $exception->getPrevious());
    }
}
