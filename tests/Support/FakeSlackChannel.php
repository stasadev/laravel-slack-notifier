<?php

namespace Stasadev\SlackNotifier\Tests\Support;

use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Notification;
use Throwable;

/**
 * Replaces the Slack webhook channel in tests.
 *
 * Like the real channel, it builds the message once per notification,
 * but it records the message and the webhook URL instead of posting them.
 */
class FakeSlackChannel
{
    /**
     * @var array<int, array{url: string|null, message: SlackMessage}>
     */
    public $sent = [];

    /**
     * @var Throwable|null
     */
    public $failWith;

    public function send($notifiable, Notification $notification): void
    {
        if ($this->failWith) {
            throw $this->failWith;
        }

        $this->sent[] = [
            'url' => $notifiable->routeNotificationFor('slack', $notification),
            'message' => $notification->toSlack($notifiable),
        ];
    }
}
