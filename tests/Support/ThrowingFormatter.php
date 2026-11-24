<?php

namespace Stasadev\SlackNotifier\Tests\Support;

use Illuminate\Notifications\Messages\SlackMessage;
use RuntimeException;
use Stasadev\SlackNotifier\Notifications\SendToSlack;
use Stasadev\SlackNotifier\SlackNotifierFormatter;

class ThrowingFormatter extends SlackNotifierFormatter
{
    public function format(SendToSlack $notification): SlackMessage
    {
        throw new RuntimeException('Cannot format this message.');
    }
}
