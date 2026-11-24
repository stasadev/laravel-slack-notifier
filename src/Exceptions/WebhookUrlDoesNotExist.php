<?php

namespace Stasadev\SlackNotifier\Exceptions;

use RuntimeException;

class WebhookUrlDoesNotExist extends RuntimeException
{
    public static function make(string $name): self
    {
        return new self("The `{$name}` webhook does not exist. Make sure you add it to the `webhook_urls.{$name}` key of the slack-notifier.php config file, or pass a valid URL.");
    }
}
