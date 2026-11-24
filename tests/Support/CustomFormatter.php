<?php

namespace Stasadev\SlackNotifier\Tests\Support;

use Illuminate\Notifications\Messages\SlackMessage;
use Stasadev\SlackNotifier\Notifications\SendToSlack;
use Stasadev\SlackNotifier\SlackNotifierFormatter;

class CustomFormatter extends SlackNotifierFormatter
{
    public function format(SendToSlack $notification): SlackMessage
    {
        return parent::format($notification)->content('Formatted by a custom formatter');
    }
}
